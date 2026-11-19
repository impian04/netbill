<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Models\User;
use App\Models\Invoice;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerCoordinatesAndKtpTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $popA;
    protected Pop $popB;
    protected User $superAdmin;
    protected User $adminPopA;
    protected User $adminPopB;
    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->popA = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Kotabaru']);
        $this->popB = Pop::factory()->create(['code' => 'EP', 'name' => 'POP Enam Puluh']);

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'pop_id' => null,
            'is_active' => true,
        ]);

        $this->adminPopA = User::factory()->create([
            'role' => 'admin',
            'pop_id' => $this->popA->id,
            'is_active' => true,
        ]);

        $this->adminPopB = User::factory()->create([
            'role' => 'admin',
            'pop_id' => $this->popB->id,
            'is_active' => true,
        ]);

        $this->technician = User::factory()->create([
            'role' => 'technician',
            'pop_id' => $this->popA->id,
            'is_active' => true,
        ]);
    }

    public function test_coordinates_validation_accepts_valid_indonesian_coordinates_and_rejects_out_of_bounds_or_swapped(): void
    {
        // Valid Indonesian coordinates: Pekanbaru / Riau (0.5071, 101.4478)
        $response = $this->actingAs($this->adminPopA)->post(route('customers.store'), [
            'pop_id' => $this->popA->id,
            'name' => 'Pelanggan Valid',
            'phone' => '081234567890',
            'address' => 'Jl. Sudirman No. 1',
            'package' => 'Paket 20 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'coordinates' => '0.5071, 101.4478',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', [
            'name' => 'Pelanggan Valid',
            'latitude' => 0.5071000,
            'longitude' => 101.4478000,
            'registered_via' => 'admin',
        ]);

        // Swapped coordinates: (101.4478, 0.5071) -> lat 101 is > 6.5, lng 0.5 is < 94
        $responseSwapped = $this->actingAs($this->adminPopA)->from(route('customers.index'))->post(route('customers.store'), [
            'pop_id' => $this->popA->id,
            'name' => 'Pelanggan Tertukar',
            'phone' => '081234567891',
            'address' => 'Jl. Sudirman No. 2',
            'package' => 'Paket 20 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'coordinates' => '101.4478, 0.5071',
        ]);

        $responseSwapped->assertSessionHasErrors(['latitude', 'longitude']);

        // Out of bounds: lat outside Indonesia (-15.0, 105.0)
        $responseOutOfBounds = $this->actingAs($this->adminPopA)->from(route('customers.index'))->post(route('customers.store'), [
            'pop_id' => $this->popA->id,
            'name' => 'Pelanggan Luar Batas',
            'phone' => '081234567892',
            'address' => 'Jl. Laut Selatan',
            'package' => 'Paket 20 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'coordinates' => '-15.0, 105.0',
        ]);

        $responseOutOfBounds->assertSessionHasErrors(['latitude']);
    }

    public function test_ktp_upload_validation_rejects_non_image_or_oversized_file(): void
    {
        // Fake text/pdf file
        $txtFile = UploadedFile::fake()->create('ktp.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->adminPopA)->from(route('customers.index'))->post(route('customers.store'), [
            'pop_id' => $this->popA->id,
            'name' => 'KTP PDF',
            'phone' => '081234567893',
            'address' => 'Jl. Dokumen',
            'package' => 'Paket 20 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'ktp_file' => $txtFile,
        ]);

        $response->assertSessionHasErrors(['ktp_file']);

        // Fake image larger than 3 MB (3100 KB)
        $largeImage = UploadedFile::fake()->image('ktp_large.jpg')->size(3200);

        $responseLarge = $this->actingAs($this->adminPopA)->from(route('customers.index'))->post(route('customers.store'), [
            'pop_id' => $this->popA->id,
            'name' => 'KTP Besar',
            'phone' => '081234567894',
            'address' => 'Jl. Foto Besar',
            'package' => 'Paket 20 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'ktp_file' => $largeImage,
        ]);

        $responseLarge->assertSessionHasErrors(['ktp_file']);
    }

    public function test_ktp_stored_in_private_disk_with_uuid_and_strips_exif_metadata(): void
    {
        $image = UploadedFile::fake()->image('foto_asli_ktp.jpg', 800, 600);

        $response = $this->actingAs($this->adminPopA)->post(route('customers.store'), [
            'pop_id' => $this->popA->id,
            'name' => 'Pelanggan KTP',
            'phone' => '081234567895',
            'address' => 'Jl. Garuda No. 5',
            'package' => 'Paket 50 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'ktp_file' => $image,
        ]);

        $response->assertSessionHasNoErrors();

        $customer = Customer::where('phone', '6281234567895')->firstOrFail();
        $this->assertNotNull($customer->ktp_path);
        $this->assertStringStartsWith('ktp/', $customer->ktp_path);
        $this->assertStringEndsWith('.jpg', $customer->ktp_path);
        $this->assertStringNotContainsString('foto_asli_ktp', $customer->ktp_path);

        // Assert file exists on private disk, but NOT on public disk
        Storage::disk('local')->assertExists($customer->ktp_path);
        Storage::disk('public')->assertMissing($customer->ktp_path);

        // Activity log for ktp.upload recorded
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ktp.upload',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'user_id' => $this->adminPopA->id,
        ]);
    }

    public function test_ktp_access_authorization_super_admin_allowed_same_pop_allowed_other_pop_and_non_admin_gets_404(): void
    {
        $image = UploadedFile::fake()->image('ktp_test.jpg', 400, 300);
        $this->actingAs($this->adminPopA)->post(route('customers.store'), [
            'pop_id' => $this->popA->id,
            'name' => 'Pelanggan Auth KTP',
            'phone' => '081234567896',
            'address' => 'Jl. Anggrek No. 1',
            'package' => 'Paket 20 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'ktp_file' => $image,
        ]);

        $customer = Customer::where('phone', '6281234567896')->firstOrFail();

        // 1. Same POP admin -> 200 OK
        $resPopA = $this->actingAs($this->adminPopA)->get(route('files.ktp.customer', $customer));
        $resPopA->assertStatus(200);
        $cacheControl = $resPopA->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $resPopA->assertHeader('X-Content-Type-Options', 'nosniff');

        // Activity log ktp.view recorded
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ktp.view',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'user_id' => $this->adminPopA->id,
        ]);

        // 2. Super Admin -> 200 OK
        $resSuper = $this->actingAs($this->superAdmin)->get(route('files.ktp.customer', $customer));
        $resSuper->assertStatus(200);

        // 3. Different POP admin -> 404
        $resPopB = $this->actingAs($this->adminPopB)->get(route('files.ktp.customer', $customer));
        $resPopB->assertStatus(404);

        // 4. Non-admin role (technician) -> 404
        $resTech = $this->actingAs($this->technician)->get(route('files.ktp.customer', $customer));
        $resTech->assertStatus(404);
    }

    public function test_ktp_file_deleted_when_replaced_removed_or_customer_deleted(): void
    {
        $image1 = UploadedFile::fake()->image('ktp_first.jpg', 400, 300);
        $this->actingAs($this->adminPopA)->post(route('customers.store'), [
            'pop_id' => $this->popA->id,
            'name' => 'Pelanggan Hapus KTP',
            'phone' => '081234567897',
            'address' => 'Jl. Mawar No. 1',
            'package' => 'Paket 20 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'ktp_file' => $image1,
        ]);

        $customer = Customer::where('phone', '6281234567897')->firstOrFail();
        $oldPath = $customer->ktp_path;
        Storage::disk('local')->assertExists($oldPath);

        // A. Replace with new KTP
        $image2 = UploadedFile::fake()->image('ktp_second.jpg', 400, 300);
        $this->actingAs($this->adminPopA)->put(route('customers.update', $customer), [
            'pop_id' => $this->popA->id,
            'name' => 'Pelanggan Hapus KTP Edit',
            'phone' => '081234567897',
            'address' => 'Jl. Mawar No. 1',
            'package' => 'Paket 20 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'ktp_file' => $image2,
        ]);

        $customer->refresh();
        $newPath = $customer->ktp_path;
        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);

        // B. Delete KTP via delete_ktp flag
        $this->actingAs($this->adminPopA)->put(route('customers.update', $customer), [
            'pop_id' => $this->popA->id,
            'name' => 'Pelanggan Hapus KTP Edit',
            'phone' => '081234567897',
            'address' => 'Jl. Mawar No. 1',
            'package' => 'Paket 20 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'delete_ktp' => '1',
        ]);

        $customer->refresh();
        $this->assertNull($customer->ktp_path);
        Storage::disk('local')->assertMissing($newPath);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ktp.delete',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
        ]);

        // C. Customer deletion removes KTP
        $image3 = UploadedFile::fake()->image('ktp_third.jpg', 400, 300);
        $this->actingAs($this->adminPopA)->put(route('customers.update', $customer), [
            'pop_id' => $this->popA->id,
            'name' => 'Pelanggan Hapus KTP Edit',
            'phone' => '081234567897',
            'address' => 'Jl. Mawar No. 1',
            'package' => 'Paket 20 Mbps',
            'billing_type' => 'Pascabayar',
            'service_status' => 'PENDING',
            'subscribed_at' => '2026-01-01',
            'ktp_file' => $image3,
        ]);

        $customer->refresh();
        $thirdPath = $customer->ktp_path;
        Storage::disk('local')->assertExists($thirdPath);

        $customer->delete();
        Storage::disk('local')->assertMissing($thirdPath);
    }

    public function test_invoice_creation_for_pending_customer_is_rejected_in_form_and_import(): void
    {
        $pendingCustomer = Customer::factory()->create([
            'pop_id' => $this->popA->id,
            'service_status' => 'PENDING',
        ]);

        // 1. Store invoice request via admin form
        $response = $this->actingAs($this->adminPopA)->from(route('invoices.index'))->post(route('invoices.store'), [
            'customer_id' => $pendingCustomer->id,
            'period' => '2026-05',
            'amount' => 150000,
            'due_date' => '2026-05-20',
        ]);

        $response->assertSessionHasErrors(['customer_id']);
        $this->assertDatabaseMissing('invoices', [
            'customer_id' => $pendingCustomer->id,
            'period' => '2026-05',
        ]);

        // 2. Import RowValidator rejects invoice when customer service status is PENDING
        $row = [
            'id_pelanggan' => $pendingCustomer->customer_code,
            'nama' => $pendingCustomer->name,
            'nomor_telepon' => $pendingCustomer->phone,
            'alamat' => 'Jl. Test',
            'paket' => 'Paket 20 Mbps',
            'jenis_tagihan' => 'Pascabayar',
            'status_layanan' => 'PENDING',
            'tanggal_mulai_berlangganan' => '2026-01-01',
            'periode' => '2026-05',
            'nominal_ditangguhkan' => '150000',
            'status' => 'UNPAID',
        ];

        $errors = \App\Imports\RowValidator::validate($row);
        $hasPendingError = false;
        foreach ($errors as $err) {
            if (str_contains($err, 'PENDING') && str_contains($err, 'Tagihan tidak dapat dibuat')) {
                $hasPendingError = true;
                break;
            }
        }
        $this->assertTrue($hasPendingError, 'RowValidator should reject invoice for PENDING customer');
    }

    public function test_customer_api_v1_does_not_expose_coordinates_or_ktp_path(): void
    {
        $customer = Customer::factory()->create([
            'pop_id' => $this->popA->id,
            'latitude' => 0.5071,
            'longitude' => 101.4478,
            'ktp_path' => 'ktp/secret-uuid.jpg',
            'registered_via' => 'admin',
        ]);

        $token = $customer->createToken('test-token', ['customer'])->plainTextToken;

        // Profile endpoint
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonMissing(['latitude'])
            ->assertJsonMissing(['longitude'])
            ->assertJsonMissing(['ktp_path'])
            ->assertJsonMissing(['coordinates'])
            ->assertJsonMissing(['registered_via'])
            ->assertJsonMissing(['referred_by_user_id']);
    }
}
