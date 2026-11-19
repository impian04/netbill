<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\CustomerApplication;
use App\Models\Pop;
use App\Models\User;
use App\Services\PrivateFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketingApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $popA;
    protected Pop $popB;
    protected User $superAdmin;
    protected User $adminPopA;
    protected User $adminPopB;
    protected User $marketingA1;
    protected User $marketingA2;
    protected User $marketingB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->popA = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Kotabaru', 'last_customer_number' => 2]);
        $this->popB = Pop::factory()->create(['code' => 'EP', 'name' => 'POP Enam Puluh', 'last_customer_number' => 0]);

        $this->superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN,
            'pop_id' => null,
            'is_active' => true,
        ]);

        $this->adminPopA = User::factory()->create([
            'role' => Role::ADMIN,
            'pop_id' => $this->popA->id,
            'is_active' => true,
        ]);

        $this->adminPopB = User::factory()->create([
            'role' => Role::ADMIN,
            'pop_id' => $this->popB->id,
            'is_active' => true,
        ]);

        $this->marketingA1 = User::factory()->create([
            'role' => Role::MARKETING,
            'pop_id' => $this->popA->id,
            'is_active' => true,
        ]);

        $this->marketingA2 = User::factory()->create([
            'role' => Role::MARKETING,
            'pop_id' => $this->popA->id,
            'is_active' => true,
        ]);

        $this->marketingB = User::factory()->create([
            'role' => Role::MARKETING,
            'pop_id' => $this->popB->id,
            'is_active' => true,
        ]);
    }

    public function test_marketing_only_views_and_edits_own_applications_and_gets_403_for_customers(): void
    {
        // 1. Marketing accessing /customers gets 403
        $response = $this->actingAs($this->marketingA1)->get(route('customers.index'));
        $response->assertStatus(403);

        // 2. Create application for marketingA1 and marketingA2
        $app1 = CustomerApplication::factory()->create([
            'pop_id' => $this->popA->id,
            'marketing_user_id' => $this->marketingA1->id,
            'name' => 'Pelanggan Milik A1',
            'status' => ApplicationStatus::SUBMITTED,
        ]);

        $app2 = CustomerApplication::factory()->create([
            'pop_id' => $this->popA->id,
            'marketing_user_id' => $this->marketingA2->id,
            'name' => 'Pelanggan Milik A2',
            'status' => ApplicationStatus::SUBMITTED,
        ]);

        // 3. MarketingA1 listing only sees app1
        $listRes = $this->actingAs($this->marketingA1)->getJson(route('marketing.applications.index'));
        $listRes->assertStatus(200)
            ->assertJsonFragment(['name' => 'Pelanggan Milik A1'])
            ->assertJsonMissing(['name' => 'Pelanggan Milik A2']);

        // 4. MarketingA1 trying to view app2 gets 404
        $viewOther = $this->actingAs($this->marketingA1)->getJson(route('marketing.applications.show', $app2));
        $viewOther->assertStatus(404);

        // 5. MarketingA1 trying to edit app2 gets 403
        $editOther = $this->actingAs($this->marketingA1)->putJson(route('marketing.applications.update', $app2), [
            'name' => 'Hacked Name',
            'phone' => '081234567890',
            'address' => 'Jl. Baru',
            'package' => '20 Mbps',
        ]);
        $editOther->assertStatus(403);
    }

    public function test_admin_pop_isolation_cannot_view_application_or_ktp_of_other_pop(): void
    {
        $file = UploadedFile::fake()->image('ktp_b.jpg');
        $ktpPath = PrivateFileService::storeKtp($file);

        $appB = CustomerApplication::factory()->create([
            'pop_id' => $this->popB->id,
            'marketing_user_id' => $this->marketingB->id,
            'name' => 'Calon POP B',
            'ktp_path' => $ktpPath,
            'status' => ApplicationStatus::SUBMITTED,
        ]);

        // Admin POP A cannot view application of POP B -> 404
        $resView = $this->actingAs($this->adminPopA)->getJson(route('customer-applications.show', $appB));
        $resView->assertStatus(404);

        // Admin POP A cannot view KTP file of POP B -> 404
        $resKtp = $this->actingAs($this->adminPopA)->get(route('files.ktp.application', $appB));
        $resKtp->assertStatus(404);

        // Super Admin can view application and KTP of POP B -> 200
        $resSuperView = $this->actingAs($this->superAdmin)->getJson(route('customer-applications.show', $appB));
        $resSuperView->assertStatus(200);

        $resSuperKtp = $this->actingAs($this->superAdmin)->get(route('files.ktp.application', $appB));
        $resSuperKtp->assertStatus(200);
    }

    public function test_approve_creates_customer_with_sequential_code_and_is_idempotent(): void
    {
        $file = UploadedFile::fake()->image('ktp_calon.jpg');
        $ktpPath = PrivateFileService::storeKtp($file);

        $app = CustomerApplication::factory()->create([
            'pop_id' => $this->popA->id,
            'marketing_user_id' => $this->marketingA1->id,
            'name' => 'Calon Disetujui',
            'phone' => '6281234567800',
            'address' => 'Jl. Pahlawan No. 10',
            'latitude' => 0.5071,
            'longitude' => 101.4478,
            'package' => '30 Mbps',
            'billing_type' => 'Bulanan',
            'ktp_path' => $ktpPath,
            'status' => ApplicationStatus::SUBMITTED,
        ]);

        // 1. Approve
        $resApprove = $this->actingAs($this->adminPopA)->postJson(route('customer-applications.approve', $app));
        $resApprove->assertStatus(200)
            ->assertJson([
                'success' => true,
                'customer_code' => 'JCN-KTU-0003',
            ]);

        $this->assertDatabaseHas('customers', [
            'customer_code' => 'JCN-KTU-0003',
            'name' => 'Calon Disetujui',
            'registered_via' => 'marketing',
            'referred_by_user_id' => $this->marketingA1->id,
            'service_status' => 'PENDING',
        ]);

        $this->popA->refresh();
        $this->assertEquals(3, $this->popA->last_customer_number);

        $app->refresh();
        $this->assertEquals(ApplicationStatus::APPROVED, $app->status);
        $this->assertNotNull($app->customer_id);

        // 2. Idempotent check: calling approve again returns same customer code without creating duplicates
        $initialCustomerCount = Customer::count();
        $resSecond = $this->actingAs($this->adminPopA)->postJson(route('customer-applications.approve', $app));
        $resSecond->assertStatus(200)
            ->assertJson([
                'success' => true,
                'customer_code' => 'JCN-KTU-0003',
            ]);

        $this->assertEquals($initialCustomerCount, Customer::count());
    }

    public function test_revision_and_rejection_require_notes(): void
    {
        $app = CustomerApplication::factory()->create([
            'pop_id' => $this->popA->id,
            'marketing_user_id' => $this->marketingA1->id,
            'status' => ApplicationStatus::SUBMITTED,
        ]);

        // Revision without review_note -> validation error
        $resRevEmpty = $this->actingAs($this->adminPopA)->postJson(route('customer-applications.request-revision', $app), [
            'review_note' => '',
        ]);
        $resRevEmpty->assertStatus(422)
            ->assertJsonValidationErrors(['review_note']);

        // Revision with review_note -> success
        $resRevOk = $this->actingAs($this->adminPopA)->postJson(route('customer-applications.request-revision', $app), [
            'review_note' => 'Mohon lengkapi patokan alamat rumah.',
        ]);
        $resRevOk->assertStatus(200);

        $app->refresh();
        $this->assertEquals(ApplicationStatus::NEEDS_REVISION, $app->status);
        $this->assertEquals('Mohon lengkapi patokan alamat rumah.', $app->review_note);

        // Rejection without review_note -> validation error
        $resRejEmpty = $this->actingAs($this->adminPopA)->postJson(route('customer-applications.reject', $app), [
            'review_note' => '',
        ]);
        $resRejEmpty->assertStatus(422)
            ->assertJsonValidationErrors(['review_note']);
    }

    public function test_reject_application_deletes_ktp_file(): void
    {
        $file = UploadedFile::fake()->image('ktp_reject.jpg');
        $ktpPath = PrivateFileService::storeKtp($file);
        Storage::disk('local')->assertExists($ktpPath);

        $app = CustomerApplication::factory()->create([
            'pop_id' => $this->popA->id,
            'marketing_user_id' => $this->marketingA1->id,
            'ktp_path' => $ktpPath,
            'status' => ApplicationStatus::SUBMITTED,
        ]);

        $res = $this->actingAs($this->adminPopA)->postJson(route('customer-applications.reject', $app), [
            'review_note' => 'Lokasi di luar jangkauan jaringan ODP.',
        ]);
        $res->assertStatus(200);

        $app->refresh();
        $this->assertEquals(ApplicationStatus::REJECTED, $app->status);
        $this->assertNull($app->ktp_path);
        Storage::disk('local')->assertMissing($ktpPath);
    }

    public function test_marketing_resubmit_flow_changes_status_back_to_submitted(): void
    {
        $app = CustomerApplication::factory()->create([
            'pop_id' => $this->popA->id,
            'marketing_user_id' => $this->marketingA1->id,
            'name' => 'Calon Perbaikan',
            'status' => ApplicationStatus::NEEDS_REVISION,
            'review_note' => 'Perbaiki nomor telepon',
        ]);

        $res = $this->actingAs($this->marketingA1)->putJson(route('marketing.applications.update', $app), [
            'name' => 'Calon Perbaikan Selesai',
            'phone' => '081234567899',
            'address' => 'Jl. Perbaikan No. 1',
            'package' => '20 Mbps',
        ]);

        $res->assertStatus(200);

        $app->refresh();
        $this->assertEquals(ApplicationStatus::SUBMITTED, $app->status);
        $this->assertEquals('Calon Perbaikan Selesai', $app->name);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'application.resubmit',
            'subject_id' => $app->id,
        ]);
    }

    public function test_duplicate_warning_does_not_leak_cross_pop_data(): void
    {
        // Customer exists in POP B
        Customer::factory()->create([
            'pop_id' => $this->popB->id,
            'customer_code' => 'JCN-EP-0001',
            'name' => 'Rahasia POP B',
            'phone' => '6281299990000',
        ]);

        // Application in POP A with the SAME phone number
        $appA = CustomerApplication::factory()->create([
            'pop_id' => $this->popA->id,
            'marketing_user_id' => $this->marketingA1->id,
            'name' => 'Calon POP A',
            'phone' => '6281299990000',
            'status' => ApplicationStatus::SUBMITTED,
        ]);

        // Admin POP A reviews appA
        $resAdminA = $this->actingAs($this->adminPopA)->getJson(route('customer-applications.show', $appA));
        $resAdminA->assertStatus(200);

        $warnings = $resAdminA->json('duplicate_warnings');
        $this->assertContains('Kemungkinan sudah terdaftar di POP lain.', $warnings);

        // Ensure NO details from POP B are leaked
        $jsonString = $resAdminA->getContent();
        $this->assertStringNotContainsString('Rahasia POP B', $jsonString);
        $this->assertStringNotContainsString('JCN-EP-0001', $jsonString);

        // Super Admin reviews appA -> can see the detailed POP B match
        $resSuper = $this->actingAs($this->superAdmin)->getJson(route('customer-applications.show', $appA));
        $resSuper->assertStatus(200);
        $superWarnings = $resSuper->json('duplicate_warnings');
        $this->assertTrue(collect($superWarnings)->contains(fn ($w) => str_contains($w, 'Rahasia POP B')));
    }

    public function test_editing_application_after_approved_is_rejected(): void
    {
        $app = CustomerApplication::factory()->create([
            'pop_id' => $this->popA->id,
            'marketing_user_id' => $this->marketingA1->id,
            'status' => ApplicationStatus::APPROVED,
        ]);

        // Marketing trying to update approved application is rejected
        $resMarketing = $this->actingAs($this->marketingA1)->putJson(route('marketing.applications.update', $app), [
            'name' => 'Ubah Nama',
            'phone' => '081234567890',
            'address' => 'Jl. Coba',
            'package' => '20 Mbps',
        ]);
        $resMarketing->assertStatus(403);

        // Admin trying to request revision or reject approved application is rejected with 422
        $resAdminRev = $this->actingAs($this->adminPopA)->postJson(route('customer-applications.request-revision', $app), [
            'review_note' => 'Revisi lagi',
        ]);
        $resAdminRev->assertStatus(422);

        $resAdminRej = $this->actingAs($this->adminPopA)->postJson(route('customer-applications.reject', $app), [
            'review_note' => 'Tolak saja',
        ]);
        $resAdminRej->assertStatus(422);
    }

    public function test_marketing_can_store_application_with_ktp_file(): void
    {
        $file = UploadedFile::fake()->image('ktp.jpg', 800, 600)->size(1500);

        $res = $this->actingAs($this->marketingA1)->post(route('marketing.applications.store'), [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
            'coordinates' => '0.5071, 101.4478',
            'package' => '50 Mbps',
            'billing_type' => 'Bulanan',
            'ktp_file' => $file,
        ], ['Accept' => 'application/json']);

        if ($res->status() === 422) {
            dump('Error with standard coordinates:', $res->json('errors'));
        }
        $res->assertStatus(200);

        // Test without coordinates (empty string)
        $res2 = $this->actingAs($this->marketingA1)->post(route('marketing.applications.store'), [
            'name' => 'Budi Santoso 2',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 2',
            'coordinates' => '',
            'package' => '50 Mbps',
            'billing_type' => 'Bulanan',
            'ktp_file' => $file,
        ], ['Accept' => 'application/json']);

        if ($res2->status() === 422) {
            dump('Error with empty coordinates:', $res2->json('errors'));
        }
        $res2->assertStatus(200);

        // Test with coordinates without comma, e.g. someone pasted "0.5071 101.4478"
        $res3 = $this->actingAs($this->marketingA1)->post(route('marketing.applications.store'), [
            'name' => 'Budi Santoso 3',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 3',
            'coordinates' => '0.5071 101.4478',
            'package' => '50 Mbps',
            'billing_type' => 'Bulanan',
            'ktp_file' => $file,
        ], ['Accept' => 'application/json']);

        if ($res3->status() === 422) {
            dump('Error with space-separated coordinates:', $res3->json('errors'));
        }

        // Test with WebP file
        $webp = UploadedFile::fake()->create('ktp.webp', 1500, 'image/webp');
        $res4 = $this->actingAs($this->marketingA1)->post(route('marketing.applications.store'), [
            'name' => 'Budi Santoso 4',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 4',
            'coordinates' => '0.5071, 101.4478',
            'package' => '50 Mbps',
            'billing_type' => 'Bulanan',
            'ktp_file' => $webp,
        ], ['Accept' => 'application/json']);

        if ($res4->status() === 422) {
            dump('Error with WebP file:', $res4->json('errors'));
        }
    }
}

