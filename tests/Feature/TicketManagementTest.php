<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketManagementTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;
    private Pop $popB;
    private User $superAdmin;
    private User $adminA;
    private User $adminB;
    private User $noc;
    private User $marketing;
    private User $surveyor;
    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->popA = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Koto Tuo']);
        $this->popB = Pop::factory()->create(['code' => 'EP',  'name' => 'POP Empat Puluh']);

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->adminA     = User::factory()->admin($this->popA)->create();
        $this->adminB     = User::factory()->admin($this->popB)->create();
        $this->noc        = User::factory()->noc()->create();
        $this->marketing  = User::factory()->marketing($this->popA)->create();
        $this->surveyor   = User::factory()->surveyor($this->popA)->create();
        $this->technician = User::factory()->technician($this->popA)->create();
    }

    public function test_access_matrix_for_tickets_page_and_routes(): void
    {
        $ticketA = Ticket::factory()->create([
            'pop_id'     => $this->popA->id,
            'created_by' => $this->noc->id,
        ]);

        // 1. Super Admin: akses penuh
        $this->actingAs($this->superAdmin)->get(route('tickets.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('tickets.show', $ticketA))->assertStatus(200);

        // 2. NOC: akses penuh (lintas POP)
        $this->actingAs($this->noc)->get(route('tickets.index'))->assertStatus(200);
        $this->actingAs($this->noc)->get(route('tickets.show', $ticketA))->assertStatus(200);

        // 3. Admin: baca saja (read-only), tidak bisa membuat/mengubah
        $this->actingAs($this->adminA)->get(route('tickets.index'))->assertStatus(200);
        $this->actingAs($this->adminA)->get(route('tickets.show', $ticketA))->assertStatus(200);

        // Admin dilarang melakukan aksi perubahan (403)
        $this->actingAs($this->adminA)->postJson(route('tickets.store'), [
            'subject' => 'Test',
        ])->assertStatus(403);

        $this->actingAs($this->adminA)->putJson(route('tickets.update', $ticketA), [
            'subject' => 'Test update',
        ])->assertStatus(403);

        $this->actingAs($this->adminA)->postJson(route('tickets.status', $ticketA), [
            'status' => TicketStatus::IN_PROGRESS->value,
        ])->assertStatus(403);

        $this->actingAs($this->adminA)->postJson(route('tickets.assign', $ticketA), [
            'assigned_to' => $this->noc->id,
        ])->assertStatus(403);

        $this->actingAs($this->adminA)->postJson(route('tickets.notes', $ticketA), [
            'body' => 'Catatan',
        ])->assertStatus(403);

        // 4. Role lain: 403 Forbidden
        $this->actingAs($this->marketing)->get(route('tickets.index'))->assertStatus(403);
        $this->actingAs($this->surveyor)->get(route('tickets.index'))->assertStatus(403);
        $this->actingAs($this->technician)->get(route('tickets.index'))->assertStatus(403);
    }

    public function test_admin_can_only_view_tickets_from_their_own_pop(): void
    {
        $ticketA = Ticket::factory()->create([
            'pop_id'     => $this->popA->id,
            'created_by' => $this->noc->id,
            'subject'    => 'Tiket POP A',
        ]);

        $ticketB = Ticket::factory()->create([
            'pop_id'     => $this->popB->id,
            'created_by' => $this->noc->id,
            'subject'    => 'Tiket POP B',
        ]);

        // Admin A bisa melihat detail tiket POP A
        $this->actingAs($this->adminA)->get(route('tickets.show', $ticketA))->assertStatus(200);

        // Admin A TIDAK bisa melihat tiket POP B (403)
        $this->actingAs($this->adminA)->get(route('tickets.show', $ticketB))->assertStatus(403);

        // Pada DataTables, Admin A hanya menerima tiket POP A
        $response = $this->actingAs($this->adminA)->getJson(route('tickets.index'));
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($ticketA->ticket_number, $data[0]['ticket_number']);
    }

    public function test_noc_can_view_and_manage_tickets_across_all_pops(): void
    {
        $ticketA = Ticket::factory()->create([
            'pop_id'     => $this->popA->id,
            'created_by' => $this->noc->id,
        ]);
        $ticketB = Ticket::factory()->create([
            'pop_id'     => $this->popB->id,
            'created_by' => $this->noc->id,
        ]);

        // NOC bisa melihat tiket dari POP A dan POP B
        $this->actingAs($this->noc)->get(route('tickets.show', $ticketA))->assertStatus(200);
        $this->actingAs($this->noc)->get(route('tickets.show', $ticketB))->assertStatus(200);

        // NOC dapat melihat semua tiket di DataTable
        $response = $this->actingAs($this->noc)->getJson(route('tickets.index'));
        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('recordsTotal'));
    }

    public function test_noc_customer_lookup_json_excludes_sensitive_fields(): void
    {
        $odc = Odc::create(['pop_id' => $this->popA->id, 'name' => 'ODC-01', 'is_active' => true]);
        $odp = Odp::create(['pop_id' => $this->popA->id, 'odc_id' => $odc->id, 'name' => 'ODP-01', 'is_active' => true]);

        $customer = Customer::factory()->create([
            'pop_id'              => $this->popA->id,
            'customer_code'       => 'JCN-KTU-0001',
            'name'                => 'Pelanggan Rahasia',
            'phone'               => '081234567890',
            'address'             => 'Jl. Merdeka No. 10',
            'package'             => 'Up to 20 Mbps',
            'service_status'      => 'ACTIVE',
            'odc_id'              => $odc->id,
            'odp_id'              => $odp->id,
            'latitude'            => 0.5100000,
            'longitude'           => 101.4400000,
            'ktp_path'            => 'ktp_photos/rahasia.jpg',
            'referred_by_user_id' => $this->marketing->id,
        ]);

        // Buat 8 invoices untuk memastikan dibatasi 6 invoices terakhir
        for ($i = 1; $i <= 8; $i++) {
            Invoice::factory()->create([
                'customer_id'     => $customer->id,
                'period'          => Carbon::create(2026, $i, 1)->format('Y-m-d'),
                'deferred_amount' => 150000,
                'status'          => 'PAID',
            ]);
        }

        $res = $this->actingAs($this->noc)->getJson(route('noc.customers.show', $customer));
        $res->assertStatus(200);

        $json = $res->json('data');

        // Field operasional yang sah
        $this->assertEquals('JCN-KTU-0001', $json['customer_code']);
        $this->assertEquals('Pelanggan Rahasia', $json['name']);
        $this->assertEquals('POP Koto Tuo', $json['pop']['name']);
        $this->assertEquals('081234567890', $json['phone']);
        $this->assertEquals('Jl. Merdeka No. 10', $json['address']);
        $this->assertEquals('Up to 20 Mbps', $json['package']);
        $this->assertEquals('ACTIVE', $json['service_status']);
        $this->assertEquals('ODC-01', $json['odc']['name']);
        $this->assertEquals('ODP-01', $json['odp']['name']);
        $this->assertEquals(0.5100000, $json['latitude']);
        $this->assertEquals(101.4400000, $json['longitude']);

        // Invoices maksimal 6
        $this->assertCount(6, $json['recent_invoices']);

        // SANGAT PENTING: TIDAK memuat field sensitif
        $this->assertArrayNotHasKey('ktp_path', $json);
        $this->assertArrayNotHasKey('referred_by_user_id', $json);
        $this->assertArrayNotHasKey('registered_via', $json);
        $this->assertArrayNotHasKey('marketing_fee', $json);
        $this->assertArrayNotHasKey('marketing_fees', $json);
        $this->assertArrayNotHasKey('marketing_profile', $json);
        $this->assertArrayNotHasKey('activation_code', $json);
        $this->assertArrayNotHasKey('password_token', $json);

        // Cek pencatatan activity log tanpa data sensitif
        $log = ActivityLog::where('action', 'VIEW_CUSTOMER_DETAIL')
            ->where('subject_id', $customer->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->noc->id, $log->user_id);
        $this->assertStringNotContainsString('1234567890123456', (string) $log->description);
    }

    public function test_ticket_status_transitions_valid_and_invalid(): void
    {
        $ticket = Ticket::factory()->create([
            'pop_id'     => $this->popA->id,
            'status'     => TicketStatus::OPEN,
            'created_by' => $this->noc->id,
        ]);

        // 1. OPEN -> IN_PROGRESS: Valid
        $res = $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status' => TicketStatus::IN_PROGRESS->value,
        ]);
        $res->assertStatus(200);
        $this->assertEquals(TicketStatus::IN_PROGRESS, $ticket->fresh()->status);

        // 2. IN_PROGRESS -> WAITING_CUSTOMER: Valid
        $res = $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status' => TicketStatus::WAITING_CUSTOMER->value,
        ]);
        $res->assertStatus(200);
        $this->assertEquals(TicketStatus::WAITING_CUSTOMER, $ticket->fresh()->status);

        // 3. WAITING_CUSTOMER -> FIELD_VISIT: Valid
        $res = $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status' => TicketStatus::FIELD_VISIT->value,
        ]);
        $res->assertStatus(200);
        $this->assertEquals(TicketStatus::FIELD_VISIT, $ticket->fresh()->status);

        // 4. FIELD_VISIT -> RESOLVED (wajib resolution_note): Valid
        $res = $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status'          => TicketStatus::RESOLVED->value,
            'resolution_note' => 'Kabel optik telah diperbaiki.',
        ]);
        $res->assertStatus(200);
        $this->assertEquals(TicketStatus::RESOLVED, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        // 5. RESOLVED -> CLOSED: Valid
        $res = $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status' => TicketStatus::CLOSED->value,
        ]);
        $res->assertStatus(200);
        $this->assertEquals(TicketStatus::CLOSED, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);

        // 6. CLOSED -> Status mana pun: Ditolak (422)
        $res = $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status' => TicketStatus::IN_PROGRESS->value,
        ]);
        $res->assertStatus(422);

        // 7. Transisi ilegal langsung dari OPEN -> CLOSED: Ditolak (422)
        $ticketOpen = Ticket::factory()->create([
            'pop_id'     => $this->popA->id,
            'status'     => TicketStatus::OPEN,
            'created_by' => $this->noc->id,
        ]);
        $resIllegal = $this->actingAs($this->noc)->postJson(route('tickets.status', $ticketOpen), [
            'status' => TicketStatus::CLOSED->value,
        ]);
        $resIllegal->assertStatus(422);
    }

    public function test_first_response_at_is_set_only_once_on_initial_transition(): void
    {
        $ticket = Ticket::factory()->create([
            'pop_id'            => $this->popA->id,
            'status'            => TicketStatus::OPEN,
            'created_by'        => $this->noc->id,
            'first_response_at' => null,
        ]);

        $this->assertNull($ticket->first_response_at);

        Carbon::setTestNow('2026-11-12 10:00:00');

        $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status' => TicketStatus::IN_PROGRESS->value,
        ])->assertStatus(200);

        $firstResponseAt = $ticket->fresh()->first_response_at;
        $this->assertNotNull($firstResponseAt);
        $this->assertEquals('2026-11-12 10:00:00', $firstResponseAt->format('Y-m-d H:i:s'));

        // Transisi berikutnya 2 jam kemudian tidak mengubah first_response_at
        Carbon::setTestNow('2026-11-12 12:00:00');

        $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status' => TicketStatus::WAITING_CUSTOMER->value,
        ])->assertStatus(200);

        $this->assertEquals('2026-11-12 10:00:00', $ticket->fresh()->first_response_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_transition_to_resolved_requires_resolution_note(): void
    {
        $ticket = Ticket::factory()->create([
            'pop_id'     => $this->popA->id,
            'status'     => TicketStatus::IN_PROGRESS,
            'created_by' => $this->noc->id,
        ]);

        // Tanpa resolution_note: Gagal 422
        $res = $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status'          => TicketStatus::RESOLVED->value,
            'resolution_note' => '',
        ]);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors('resolution_note');

        // Dengan resolution_note: Berhasil
        $resSuccess = $this->actingAs($this->noc)->postJson(route('tickets.status', $ticket), [
            'status'          => TicketStatus::RESOLVED->value,
            'resolution_note' => 'Redaman optik telah dinormalkan ke -20 dBm.',
        ]);
        $resSuccess->assertStatus(200);
        $this->assertEquals(TicketStatus::RESOLVED, $ticket->fresh()->status);
        $this->assertEquals('Redaman optik telah dinormalkan ke -20 dBm.', $ticket->fresh()->resolution_note);
    }

    public function test_ticket_number_is_unique_and_formatted_correctly(): void
    {
        $customer = Customer::factory()->create(['pop_id' => $this->popA->id]);

        $t1 = Ticket::createWithNumber([
            'customer_id'    => $customer->id,
            'pop_id'         => $customer->pop_id,
            'reporter_name'  => $customer->name,
            'reporter_phone' => '081234567890',
            'category'       => TicketCategory::CONNECTION,
            'channel'        => TicketChannel::WHATSAPP,
            'priority'       => TicketPriority::NORMAL,
            'subject'        => 'Kendala koneksi lambat',
            'description'    => 'Speedtest hanya tembus 2 Mbps.',
            'status'         => TicketStatus::OPEN,
            'created_by'     => $this->noc->id,
        ]);

        $t2 = Ticket::createWithNumber([
            'customer_id'    => $customer->id,
            'pop_id'         => $customer->pop_id,
            'reporter_name'  => $customer->name,
            'reporter_phone' => '081234567890',
            'category'       => TicketCategory::OTHER,
            'channel'        => TicketChannel::PHONE,
            'priority'       => TicketPriority::LOW,
            'subject'        => 'Pertanyaan teknis',
            'description'    => 'Menanyakan IP Public statis.',
            'status'         => TicketStatus::OPEN,
            'created_by'     => $this->noc->id,
        ]);

        $expectedPrefix = 'TKT-' . now()->format('Ym') . '-';
        $this->assertStringStartsWith($expectedPrefix, $t1->ticket_number);
        $this->assertStringStartsWith($expectedPrefix, $t2->ticket_number);
        $this->assertNotEquals($t1->ticket_number, $t2->ticket_number);
        $this->assertMatchesRegularExpression('/^TKT-\d{6}-\d{6}$/', $t1->ticket_number);
    }

    public function test_ticket_for_non_customer_requires_reporter_name_and_phone_and_pop(): void
    {
        // 1. Submit tanpa customer_id dan tanpa data pelapor: Gagal validasi 422
        $res = $this->actingAs($this->noc)->postJson(route('tickets.store'), [
            'category'    => TicketCategory::OTHER->value,
            'channel'     => TicketChannel::WALK_IN->value,
            'priority'    => TicketPriority::NORMAL->value,
            'subject'     => 'Pertanyaan Umum',
            'description' => 'Mampir ke kantor menanyakan paket.',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['pop_id', 'reporter_name', 'reporter_phone']);

        // 2. Submit dengan data pelapor umum lengkap: Berhasil 200
        $resSuccess = $this->actingAs($this->noc)->postJson(route('tickets.store'), [
            'pop_id'         => $this->popA->id,
            'reporter_name'  => 'Budi Santoso',
            'reporter_phone' => '081298765432',
            'category'       => TicketCategory::OTHER->value,
            'channel'        => TicketChannel::WALK_IN->value,
            'priority'       => TicketPriority::NORMAL->value,
            'subject'        => 'Pertanyaan Umum',
            'description'    => 'Mampir ke kantor menanyakan paket.',
        ]);

        $resSuccess->assertStatus(200);
        $this->assertDatabaseHas('tickets', [
            'customer_id'    => null,
            'reporter_name'  => 'Budi Santoso',
            'reporter_phone' => '081298765432',
            'pop_id'         => $this->popA->id,
        ]);
    }

    public function test_noc_customer_search_is_rate_limited(): void
    {
        Customer::factory()->create([
            'pop_id'        => $this->popA->id,
            'name'          => 'Andi Wijaya',
            'customer_code' => 'JCN-ANDI-001',
        ]);

        // Hit 30 kali aman
        for ($i = 0; $i < 30; $i++) {
            $response = $this->actingAs($this->noc)->getJson(route('noc.customers.search', ['q' => 'Andi']));
            $this->assertEquals(200, $response->status(), "Percobaan ke-{$i} harus 200 OK");
        }

        // Percobaan ke-31 harus menerima 429 Too Many Requests
        $response31 = $this->actingAs($this->noc)->getJson(route('noc.customers.search', ['q' => 'Andi']));
        $response31->assertStatus(429);
    }

    public function test_customer_search_reports_open_tickets_count_warning(): void
    {
        $customer = Customer::factory()->create([
            'pop_id'        => $this->popA->id,
            'name'          => 'Riko Pratama',
            'customer_code' => 'JCN-KTU-0099',
        ]);

        // Buat 2 tiket status aktif (OPEN & IN_PROGRESS)
        Ticket::factory()->create([
            'customer_id' => $customer->id,
            'pop_id'      => $customer->pop_id,
            'status'      => TicketStatus::OPEN,
            'created_by'  => $this->noc->id,
        ]);
        Ticket::factory()->create([
            'customer_id' => $customer->id,
            'pop_id'      => $customer->pop_id,
            'status'      => TicketStatus::IN_PROGRESS,
            'created_by'  => $this->noc->id,
        ]);

        // Tiket yang sudah resolved (tidak dihitung sebagai open)
        Ticket::factory()->create([
            'customer_id'     => $customer->id,
            'pop_id'          => $customer->pop_id,
            'status'          => TicketStatus::RESOLVED,
            'resolution_note' => 'Selesai',
            'created_by'      => $this->noc->id,
        ]);

        $res = $this->actingAs($this->noc)->getJson(route('tickets.search-customers', ['q' => 'Riko']));
        $res->assertStatus(200);

        $data = $res->json();
        $this->assertNotEmpty($data);
        $this->assertEquals($customer->id, $data[0]['id']);
        $this->assertEquals(2, $data[0]['open_tickets_count']);
    }
}
