<?php

namespace Tests\Feature;

use App\Enums\PhotoStage;
use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Customer;
use App\Models\MarketingFee;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairWorkOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;
    private Pop $popB;
    private User $superAdmin;
    private User $adminA;
    private User $adminB;
    private User $noc;
    private User $techA;
    private User $techB;
    private Customer $customerA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->popA = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Koto Tuo']);
        $this->popB = Pop::factory()->create(['code' => 'EP',  'name' => 'POP Empat Puluh']);

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->adminA     = User::factory()->admin($this->popA)->create();
        $this->adminB     = User::factory()->admin($this->popB)->create();
        $this->noc        = User::factory()->noc()->create();
        $this->techA      = User::factory()->technician($this->popA)->create();
        $this->techB      = User::factory()->technician($this->popB)->create();

        $this->customerA  = Customer::factory()->create([
            'pop_id'         => $this->popA->id,
            'customer_code'  => 'JCN-KTU-001',
            'name'           => 'Budi Pelanggan',
            'service_status' => 'ACTIVE',
            'address'        => 'Jl. Anggrek No. 5',
            'phone'          => '081234567890',
            'latitude'       => 0.5100000,
            'longitude'      => 101.4400000,
            'ktp_path'       => 'ktp_photos/budi.jpg',
        ]);
    }

    public function test_noc_can_request_field_visit_for_in_progress_ticket(): void
    {
        $ticket = Ticket::createWithNumber([
            'customer_id'       => $this->customerA->id,
            'pop_id'            => $this->popA->id,
            'reporter_name'     => $this->customerA->name,
            'reporter_phone'    => $this->customerA->phone,
            'category'          => TicketCategory::CONNECTION,
            'channel'           => TicketChannel::WHATSAPP,
            'priority'          => TicketPriority::HIGH,
            'subject'           => 'Kabel optik putus tertimpa dahan',
            'description'       => 'Indikator LOS merah mati total sejak sore.',
            'status'            => TicketStatus::IN_PROGRESS,
            'created_by'        => $this->noc->id,
            'first_response_at' => now(),
        ]);

        $res = $this->actingAs($this->noc)->postJson(route('tickets.request-field-visit', $ticket), [
            'admin_note' => 'Periksa kabel dropcore dari ODP tiang 3 ke rumah pelanggan.',
        ]);

        $res->assertStatus(200);

        // Tiket menjadi FIELD_VISIT
        $this->assertEquals(TicketStatus::FIELD_VISIT, $ticket->fresh()->status);

        // Work Order REPAIR dibuat dengan status REQUESTED
        $this->assertDatabaseHas('work_orders', [
            'type'               => WorkOrderType::REPAIR->value,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => null,
            'ticket_id'          => $ticket->id,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => null,
            'status'             => WorkOrderStatus::REQUESTED->value,
        ]);

        // Timeline update tercatat pada tiket
        $this->assertDatabaseHas('ticket_updates', [
            'ticket_id'   => $ticket->id,
            'from_status' => TicketStatus::IN_PROGRESS->value,
            'to_status'   => TicketStatus::FIELD_VISIT->value,
        ]);
    }

    public function test_field_visit_rejected_if_conditions_not_met(): void
    {
        // 1. Tiket tanpa pelanggan (pelapor umum): ditolak
        $ticketNoCust = Ticket::createWithNumber([
            'customer_id'    => null,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => 'Pelapor Umum',
            'reporter_phone' => '081299887766',
            'category'       => TicketCategory::CONNECTION,
            'channel'        => TicketChannel::PHONE,
            'priority'       => TicketPriority::NORMAL,
            'subject'        => 'Tanya jaringan',
            'description'    => 'Menanyakan jaringan di jalan raya.',
            'status'         => TicketStatus::IN_PROGRESS,
            'created_by'     => $this->noc->id,
        ]);

        $this->actingAs($this->noc)->postJson(route('tickets.request-field-visit', $ticketNoCust), [
            'admin_note' => 'Cek lokasi',
        ])->assertStatus(422);

        // 2. Kategori PAYMENT: ditolak
        $ticketPayment = Ticket::createWithNumber([
            'customer_id'    => $this->customerA->id,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => $this->customerA->name,
            'reporter_phone' => $this->customerA->phone,
            'category'       => TicketCategory::PAYMENT,
            'channel'        => TicketChannel::PHONE,
            'priority'       => TicketPriority::NORMAL,
            'subject'        => 'Kendala tagihan',
            'description'    => 'Salah nominal transfer.',
            'status'         => TicketStatus::IN_PROGRESS,
            'created_by'     => $this->noc->id,
        ]);

        $this->actingAs($this->noc)->postJson(route('tickets.request-field-visit', $ticketPayment), [
            'admin_note' => 'Cek lokasi',
        ])->assertStatus(422);

        // 3. Status masih OPEN: ditolak
        $ticketOpen = Ticket::createWithNumber([
            'customer_id'    => $this->customerA->id,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => $this->customerA->name,
            'reporter_phone' => $this->customerA->phone,
            'category'       => TicketCategory::CONNECTION,
            'channel'        => TicketChannel::PHONE,
            'priority'       => TicketPriority::NORMAL,
            'subject'        => 'Koneksi lambat',
            'description'    => 'Lambat sejak pagi.',
            'status'         => TicketStatus::OPEN,
            'created_by'     => $this->noc->id,
        ]);

        $this->actingAs($this->noc)->postJson(route('tickets.request-field-visit', $ticketOpen), [
            'admin_note' => 'Cek lokasi',
        ])->assertStatus(422);

        // 4. Status pelanggan TERMINATED: ditolak
        $this->customerA->update(['service_status' => 'TERMINATED']);
        $ticketTerminated = Ticket::createWithNumber([
            'customer_id'    => $this->customerA->id,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => $this->customerA->name,
            'reporter_phone' => $this->customerA->phone,
            'category'       => TicketCategory::CONNECTION,
            'channel'        => TicketChannel::PHONE,
            'priority'       => TicketPriority::NORMAL,
            'subject'        => 'Koneksi putus',
            'description'    => 'Pelanggan sudah berhenti tapi minta cek.',
            'status'         => TicketStatus::IN_PROGRESS,
            'created_by'     => $this->noc->id,
        ]);

        $this->actingAs($this->noc)->postJson(route('tickets.request-field-visit', $ticketTerminated), [
            'admin_note' => 'Cek lokasi',
        ])->assertStatus(422);
    }

    public function test_only_one_active_field_visit_allowed_per_ticket(): void
    {
        $ticket = Ticket::createWithNumber([
            'customer_id'    => $this->customerA->id,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => $this->customerA->name,
            'reporter_phone' => $this->customerA->phone,
            'category'       => TicketCategory::CONNECTION,
            'channel'        => TicketChannel::WHATSAPP,
            'priority'       => TicketPriority::HIGH,
            'subject'        => 'Koneksi bermasalah',
            'description'    => 'LOS merah.',
            'status'         => TicketStatus::IN_PROGRESS,
            'created_by'     => $this->noc->id,
        ]);

        // Request pertama: Berhasil
        $this->actingAs($this->noc)->postJson(route('tickets.request-field-visit', $ticket), [
            'admin_note' => 'Instruksi pertama untuk teknisi.',
        ])->assertStatus(200);

        // Request kedua saat WO pertama masih aktif: Ditolak 422
        $res2 = $this->actingAs($this->noc)->postJson(route('tickets.request-field-visit', $ticket), [
            'admin_note' => 'Instruksi kedua.',
        ]);
        $res2->assertStatus(422);
        $this->assertStringContainsString('sudah memiliki penugasan', $res2->json('message'));
    }

    public function test_admin_pop_can_assign_technician_to_requested_repair(): void
    {
        $ticket = Ticket::createWithNumber([
            'customer_id'    => $this->customerA->id,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => $this->customerA->name,
            'reporter_phone' => $this->customerA->phone,
            'category'       => TicketCategory::CONNECTION,
            'channel'        => TicketChannel::WHATSAPP,
            'priority'       => TicketPriority::HIGH,
            'subject'        => 'ONT Rusak tersambar petir',
            'description'    => 'Perlu ganti modem ONT.',
            'status'         => TicketStatus::FIELD_VISIT,
            'created_by'     => $this->noc->id,
        ]);

        $wo = WorkOrder::create([
            'type'               => WorkOrderType::REPAIR,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => null,
            'ticket_id'          => $ticket->id,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => null,
            'status'             => WorkOrderStatus::REQUESTED,
            'admin_note'         => 'Bawa ONT cadangan ZTE.',
        ]);

        // Admin A menugaskan teknisi A
        $res = $this->actingAs($this->adminA)->postJson(route('work-orders.assign-repair', $wo), [
            'technician_user_id' => $this->techA->id,
            'scheduled_date'     => now()->addDay()->format('Y-m-d'),
            'admin_note'         => 'Bawa ONT cadangan ZTE dan patchcord 3m.',
        ]);

        $res->assertStatus(200);

        $freshWo = $wo->fresh();
        $this->assertEquals(WorkOrderStatus::ASSIGNED, $freshWo->status);
        $this->assertEquals($this->techA->id, $freshWo->technician_user_id);
        $this->assertEquals($this->adminA->id, $freshWo->assigned_by);

        // Catatan penugasan masuk ke timeline tiket
        $this->assertDatabaseHas('ticket_updates', [
            'ticket_id' => $ticket->id,
            'type'      => 'ASSIGNMENT',
        ]);
    }

    public function test_admin_of_other_pop_cannot_view_or_assign_repair_work_order(): void
    {
        $wo = WorkOrder::create([
            'type'               => WorkOrderType::REPAIR,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => null,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => null,
            'status'             => WorkOrderStatus::REQUESTED,
            'admin_note'         => 'Perbaikan POP A',
        ]);

        // Admin B (POP B) mencoba melihat: 404
        $this->actingAs($this->adminB)->getJson(route('work-orders.show', $wo))->assertStatus(404);

        // Admin B mencoba menugaskan: 404
        $this->actingAs($this->adminB)->postJson(route('work-orders.assign-repair', $wo), [
            'technician_user_id' => $this->techB->id,
        ])->assertStatus(404);
    }

    public function test_admin_can_reject_repair_visit_and_ticket_returns_to_in_progress(): void
    {
        $ticket = Ticket::createWithNumber([
            'customer_id'    => $this->customerA->id,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => $this->customerA->name,
            'reporter_phone' => $this->customerA->phone,
            'category'       => TicketCategory::CONNECTION,
            'channel'        => TicketChannel::WHATSAPP,
            'priority'       => TicketPriority::NORMAL,
            'subject'        => 'Minta reset password wifi',
            'description'    => 'Pelanggan minta teknisi datang hanya untuk ubah SSID.',
            'status'         => TicketStatus::FIELD_VISIT,
            'created_by'     => $this->noc->id,
        ]);

        $wo = WorkOrder::create([
            'type'               => WorkOrderType::REPAIR,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => null,
            'ticket_id'          => $ticket->id,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => null,
            'status'             => WorkOrderStatus::REQUESTED,
            'admin_note'         => 'Reset password wifi.',
        ]);

        // Admin menolak kunjungan teknisi karena bisa dipandu via telepon
        $res = $this->actingAs($this->adminA)->postJson(route('work-orders.reject-repair', $wo), [
            'reason' => 'Tidak perlu kunjungan teknisi, kendala ini dapat dipandu remote via telepon oleh NOC.',
        ]);

        $res->assertStatus(200);

        // Work order dibatalkan
        $this->assertEquals(WorkOrderStatus::CANCELLED, $wo->fresh()->status);

        // Tiket kembali ke IN_PROGRESS
        $this->assertEquals(TicketStatus::IN_PROGRESS, $ticket->fresh()->status);

        // Timeline mencatat penolakan
        $this->assertDatabaseHas('ticket_updates', [
            'ticket_id'   => $ticket->id,
            'from_status' => TicketStatus::FIELD_VISIT->value,
            'to_status'   => TicketStatus::IN_PROGRESS->value,
        ]);
    }

    public function test_full_repair_lifecycle_technician_documentation_and_admin_verification(): void
    {
        $ticket = Ticket::createWithNumber([
            'customer_id'    => $this->customerA->id,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => $this->customerA->name,
            'reporter_phone' => $this->customerA->phone,
            'category'       => TicketCategory::CONNECTION,
            'channel'        => TicketChannel::WHATSAPP,
            'priority'       => TicketPriority::HIGH,
            'subject'        => 'Redaman tinggi -32 dBm',
            'description'    => 'Koneksi sering RTO.',
            'status'         => TicketStatus::FIELD_VISIT,
            'created_by'     => $this->noc->id,
        ]);

        $wo = WorkOrder::create([
            'type'               => WorkOrderType::REPAIR,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => null,
            'ticket_id'          => $ticket->id,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => $this->techA->id,
            'status'             => WorkOrderStatus::ASSIGNED,
            'scheduled_date'     => now()->format('Y-m-d'),
            'admin_note'         => 'Splicing ulang konektor tiang.',
            'assigned_by'        => $this->adminA->id,
        ]);

        // 1. Teknisi mulai pekerjaan (IN_PROGRESS)
        $this->actingAs($this->techA)->postJson(route('technician.tasks.start', $wo))->assertStatus(200);
        $this->assertEquals(WorkOrderStatus::IN_PROGRESS, $wo->fresh()->status);

        // 2. Teknisi unggah 1 foto PERBAIKAN
        $file = UploadedFile::fake()->image('hasil_splicing.jpg', 600, 600);
        $resPhoto = $this->actingAs($this->techA)->postJson(route('technician.tasks.photos.upload', $wo), [
            'photo'   => $file,
            'stage'   => PhotoStage::PERBAIKAN->value,
            'caption' => 'Splicing ulang dropcore di tiang ODP selesai, redaman kini -19 dBm.',
        ]);
        $resPhoto->assertStatus(201);
        $this->assertEquals(1, $wo->photos()->count());

        // 3. Teknisi kirim laporan pekerjaan (SUBMITTED)
        $resSubmit = $this->actingAs($this->techA)->postJson(route('technician.tasks.submit', $wo), [
            'technician_notes' => 'Redaman sudah normal -19.2 dBm, internet lancar dites dengan HP pelanggan.',
            'completed_on'     => now()->format('Y-m-d'),
        ]);
        $resSubmit->assertStatus(200);
        $this->assertEquals(WorkOrderStatus::SUBMITTED, $wo->fresh()->status);

        // Catat kondisi awal pelanggan sebelum verifikasi
        $initialSubscribedAt = $this->customerA->subscribed_at;
        $initialServiceStatus = $this->customerA->service_status;

        // 4. Admin POP memverifikasi pekerjaan perbaikan (VERIFIED)
        $resVerify = $this->actingAs($this->adminA)->postJson(route('work-orders.verify', $wo));
        $resVerify->assertStatus(200);

        $freshWo = $wo->fresh();
        $this->assertEquals(WorkOrderStatus::VERIFIED, $freshWo->status);
        $this->assertEquals($this->adminA->id, $freshWo->verified_by);

        // PENTING: Tiket kembali ke IN_PROGRESS dengan catatan timeline "Kunjungan selesai"
        $this->assertEquals(TicketStatus::IN_PROGRESS, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_updates', [
            'ticket_id'   => $ticket->id,
            'from_status' => TicketStatus::FIELD_VISIT->value,
            'to_status'   => TicketStatus::IN_PROGRESS->value,
            'body'        => 'Kunjungan selesai',
        ]);

        // PENTING: Verifikasi REPAIR TIDAK mengubah status pelanggan atau tanggal langganan
        $freshCustomer = $this->customerA->fresh();
        $this->assertEquals($initialServiceStatus, $freshCustomer->service_status);
        $this->assertEquals($initialSubscribedAt, $freshCustomer->subscribed_at);

        // PENTING: Verifikasi REPAIR TIDAK membuat marketing fee
        $this->assertEquals(0, MarketingFee::count());

        // 5. NOC kini bisa menyelesaikan tiket (RESOLVED)
        $resResolved = $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status'          => TicketStatus::RESOLVED->value,
            'resolution_note' => 'Perbaikan kabel selesai dikonfirmasi pelanggan dan redaman optik stabil.',
        ]);
        $resResolved->assertStatus(200);
        $this->assertEquals(TicketStatus::RESOLVED, $ticket->fresh()->status);
    }

    public function test_repair_allows_minimum_1_photo_while_installation_requires_2_photos(): void
    {
        // REPAIR work order
        $woRepair = WorkOrder::create([
            'type'               => WorkOrderType::REPAIR,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => null,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => $this->techA->id,
            'status'             => WorkOrderStatus::IN_PROGRESS,
        ]);

        // 0 foto -> submit gagal
        $this->actingAs($this->techA)->postJson(route('technician.tasks.submit', $woRepair))->assertStatus(422);

        // 1 foto -> submit berhasil untuk REPAIR
        WorkOrderPhoto::create([
            'work_order_id' => $woRepair->id,
            'path'          => 'photos/repair.jpg',
            'stage'         => PhotoStage::PERBAIKAN,
            'uploaded_by'   => $this->techA->id,
        ]);
        $this->actingAs($this->techA)->postJson(route('technician.tasks.submit', $woRepair))->assertStatus(200);

        // INSTALLATION work order
        $cust2 = Customer::factory()->create(['pop_id' => $this->popA->id]);
        $woInstall = WorkOrder::create([
            'type'               => WorkOrderType::INSTALLATION,
            'customer_id'        => $cust2->id,
            'installation_key'   => $cust2->id,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => $this->techA->id,
            'status'             => WorkOrderStatus::IN_PROGRESS,
        ]);

        // 1 foto untuk INSTALLATION -> submit gagal (butuh min 2 foto)
        WorkOrderPhoto::create([
            'work_order_id' => $woInstall->id,
            'path'          => 'photos/install1.jpg',
            'stage'         => PhotoStage::INSTALASI,
            'uploaded_by'   => $this->techA->id,
        ]);
        $this->actingAs($this->techA)->postJson(route('technician.tasks.submit', $woInstall))->assertStatus(422);

        // 2 foto untuk INSTALLATION -> submit berhasil
        WorkOrderPhoto::create([
            'work_order_id' => $woInstall->id,
            'path'          => 'photos/install2.jpg',
            'stage'         => PhotoStage::AKTIVASI,
            'uploaded_by'   => $this->techA->id,
        ]);
        $this->actingAs($this->techA)->postJson(route('technician.tasks.submit', $woInstall))->assertStatus(200);
    }

    public function test_customer_can_have_multiple_repairs_but_only_one_installation(): void
    {
        // 1. Buat WO INSTALLATION pertama
        $woInstall1 = WorkOrder::create([
            'type'               => WorkOrderType::INSTALLATION,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => $this->customerA->id,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => $this->techA->id,
            'status'             => WorkOrderStatus::VERIFIED,
        ]);
        $this->assertNotNull($woInstall1->id);

        // 2. Buat WO REPAIR 1 untuk pelanggan yang sama -> Berhasil
        $woRepair1 = WorkOrder::create([
            'type'               => WorkOrderType::REPAIR,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => null,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => $this->techA->id,
            'status'             => WorkOrderStatus::VERIFIED,
        ]);
        $this->assertNotNull($woRepair1->id);

        // 3. Buat WO REPAIR 2 untuk pelanggan yang sama -> Berhasil
        $woRepair2 = WorkOrder::create([
            'type'               => WorkOrderType::REPAIR,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => null,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => $this->techA->id,
            'status'             => WorkOrderStatus::REQUESTED,
        ]);
        $this->assertNotNull($woRepair2->id);

        // 4. Mencoba membuat WO INSTALLATION kedua untuk pelanggan yang sama memicu pelanggaran kunci unik
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        WorkOrder::create([
            'type'               => WorkOrderType::INSTALLATION,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => $this->customerA->id,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => $this->techA->id,
            'status'             => WorkOrderStatus::ASSIGNED,
        ]);
    }

    public function test_technician_api_excludes_sensitive_customer_data(): void
    {
        $ticket = Ticket::createWithNumber([
            'customer_id'    => $this->customerA->id,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => $this->customerA->name,
            'reporter_phone' => $this->customerA->phone,
            'category'       => TicketCategory::CONNECTION,
            'channel'        => TicketChannel::WHATSAPP,
            'priority'       => TicketPriority::HIGH,
            'subject'        => 'Kabel putus',
            'description'    => 'Tertimpa pohon.',
            'status'         => TicketStatus::FIELD_VISIT,
            'created_by'     => $this->noc->id,
        ]);

        $wo = WorkOrder::create([
            'type'               => WorkOrderType::REPAIR,
            'customer_id'        => $this->customerA->id,
            'installation_key'   => null,
            'ticket_id'          => $ticket->id,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => $this->techA->id,
            'status'             => WorkOrderStatus::ASSIGNED,
            'admin_note'         => 'Instruksi admin',
        ]);

        $res = $this->actingAs($this->techA)->getJson(route('technician.tasks.show', $wo));
        $res->assertStatus(200);

        $json = $res->json('work_order');

        // Data teknis yang sah
        $this->assertEquals('Budi Pelanggan', $json['customer_name']);
        $this->assertEquals('Jl. Anggrek No. 5', $json['customer_address']);
        $this->assertEquals('081234567890', $json['customer_phone']);
        $this->assertEquals('REPAIR', $json['type']);
        $this->assertEquals($ticket->ticket_number, $json['ticket_number']);
        $this->assertEquals('Kabel putus', $json['ticket_subject']);
        $this->assertEquals('Tertimpa pohon.', $json['ticket_description']);

        // Data sensitif TIDAK ADA
        $this->assertArrayNotHasKey('ktp_path', $json);
        $this->assertArrayNotHasKey('ktp_number', $json);
        $this->assertArrayNotHasKey('invoices', $json);
        $this->assertArrayNotHasKey('marketing_fee', $json);
        $this->assertArrayNotHasKey('activation_code', $json);
    }
}

