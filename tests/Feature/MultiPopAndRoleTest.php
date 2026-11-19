<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\ProcessImport;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\User;
use App\Services\ActivationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiPopAndRoleTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;
    private Pop $popB;
    private User $superAdmin;
    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->popA = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Koto Tuo', 'last_customer_number' => 0]);
        $this->popB = Pop::factory()->create(['code' => 'EP', 'name' => 'POP Empat Petaling', 'last_customer_number' => 0]);

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email' => 'superadmin@netbill.test',
        ]);

        $this->adminA = User::factory()->admin($this->popA)->create([
            'email' => 'admin.ktu@netbill.test',
        ]);
    }

    public function test_access_matrix_for_roles_on_key_routes(): void
    {
        // 1. Super Admin access
        $this->actingAs($this->superAdmin)->get(route('customers.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('invoices.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('imports.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('pops.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('staff.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('activity-logs.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('announcements.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('settings.noc'))->assertStatus(200);

        // 2. Admin POP access
        $this->actingAs($this->adminA)->get(route('customers.index'))->assertStatus(200);
        $this->actingAs($this->adminA)->get(route('invoices.index'))->assertStatus(200);
        $this->actingAs($this->adminA)->get(route('imports.index'))->assertStatus(200);
        $this->actingAs($this->adminA)->get(route('pops.index'))->assertStatus(403);
        $this->actingAs($this->adminA)->get(route('staff.index'))->assertStatus(403);
        $this->actingAs($this->adminA)->get(route('activity-logs.index'))->assertStatus(403);
        $this->actingAs($this->adminA)->get(route('announcements.index'))->assertStatus(403);
        $this->actingAs($this->adminA)->get(route('settings.noc'))->assertStatus(403);

        // 3. Non-admin staff (marketing, surveyor, technician, noc)
        $marketing = User::factory()->marketing($this->popA)->create();
        $this->actingAs($marketing)->get(route('portal'))->assertRedirect(route('marketing.applications.index'));
        $this->actingAs($marketing)->get(route('customers.index'))->assertStatus(403);
        $this->actingAs($marketing)->get(route('invoices.index'))->assertStatus(403);
        $this->actingAs($marketing)->get(route('pops.index'))->assertStatus(403);

        $noc = User::factory()->noc()->create();
        $this->actingAs($noc)->get(route('portal'))->assertRedirect(route('tickets.index'));
        $this->actingAs($noc)->get(route('tickets.index'))->assertStatus(200);

        // Login non-admin redirects to landing page per role
        auth()->logout();
        $this->post('/login', [
            'email'    => $marketing->email,
            'password' => 'password',
        ])->assertRedirect(route('marketing.applications.index'));
    }

    public function test_pop_isolation_admin_cannot_see_edit_delete_or_export_other_pop(): void
    {
        $customerA = Customer::factory()->create([
            'pop_id'        => $this->popA->id,
            'customer_code' => 'JCN-KTU-0001',
            'name'          => 'Pelanggan Koto Tuo',
        ]);

        $customerB = Customer::factory()->create([
            'pop_id'        => $this->popB->id,
            'customer_code' => 'JCN-EP-0001',
            'name'          => 'Pelanggan Empat Petaling',
        ]);

        $invoiceB = Invoice::factory()->create([
            'customer_id' => $customerB->id,
        ]);

        // List customers as Admin A: should only see Customer A
        $res = $this->actingAs($this->adminA)->getJson(route('customers.index'));
        $res->assertStatus(200);
        $res->assertJsonFragment(['name' => 'Pelanggan Koto Tuo']);
        $res->assertJsonMissing(['name' => 'Pelanggan Empat Petaling']);

        // Direct show, update, delete Customer B by Admin A -> 404
        $this->actingAs($this->adminA)->getJson(route('customers.show', $customerB))->assertStatus(404);
        $this->actingAs($this->adminA)->putJson(route('customers.update', $customerB), [
            'name'           => 'Hacked Name',
            'phone'          => '628123456789',
            'address'        => 'Jl. Baru',
            'subscribed_at'  => '2026-01-01',
            'package'        => '10 Mbps',
            'billing_type'   => 'Bulanan',
            'service_status' => 'ACTIVE',
        ])->assertStatus(404);
        $this->actingAs($this->adminA)->deleteJson(route('customers.destroy', $customerB))->assertStatus(404);

        // Direct show invoice B by Admin A -> 404
        $this->actingAs($this->adminA)->getJson(route('invoices.show', $invoiceB))->assertStatus(404);

        // Export customers: only Customer A included, Customer B excluded
        $exportCustRes = $this->actingAs($this->adminA)->get(route('export.customers'));
        $exportCustRes->assertStatus(200);
        $custContent = $exportCustRes->streamedContent();
        $this->assertStringContainsString('Pelanggan Koto Tuo', $custContent);
        $this->assertStringNotContainsString('Pelanggan Empat Petaling', $custContent);

        // Export invoices: only Invoice of Customer A included if any, Invoice B excluded
        $exportInvRes = $this->actingAs($this->adminA)->get(route('export.invoices'));
        $exportInvRes->assertStatus(200);
        $invContent = $exportInvRes->streamedContent();
        $this->assertStringNotContainsString($invoiceB->invoice_number, $invContent);
    }

    public function test_import_batches_isolation_between_pops(): void
    {
        $adminB = User::factory()->admin($this->popB)->create([
            'email' => 'admin.ep@netbill.test',
        ]);

        $batchA = ImportBatch::create([
            'user_id'          => $this->adminA->id,
            'pop_id'           => $this->popA->id,
            'original_name'    => 'batch_pop_a.csv',
            'file_path'        => 'imports/batch_a.csv',
            'duplicate_policy' => 'skip',
            'status'           => 'done',
        ]);

        $batchB = ImportBatch::create([
            'user_id'          => $adminB->id,
            'pop_id'           => $this->popB->id,
            'original_name'    => 'batch_pop_b.csv',
            'file_path'        => 'imports/batch_b.csv',
            'duplicate_policy' => 'skip',
            'status'           => 'done',
        ]);

        // 1. Admin A sees only Batch A in imports.index
        $indexRes = $this->actingAs($this->adminA)->get(route('imports.index'));
        $indexRes->assertStatus(200);
        $indexRes->assertSee('batch_pop_a.csv');
        $indexRes->assertDontSee('batch_pop_b.csv');

        // 2. Admin A accessing Batch B directly -> 404
        $this->actingAs($this->adminA)->get(route('imports.show', $batchB))->assertStatus(404);
        $this->actingAs($this->adminA)->getJson(route('imports.show', $batchB))->assertStatus(404);

        // 3. Admin A accessing Batch A -> 200
        $this->actingAs($this->adminA)->get(route('imports.show', $batchA))->assertStatus(200);
        $this->actingAs($this->adminA)->getJson(route('imports.show', $batchA))->assertStatus(200);

        // 4. Super Admin can view all batches
        $this->actingAs($this->superAdmin)->get(route('imports.show', $batchA))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('imports.show', $batchB))->assertStatus(200);
    }

    public function test_customer_code_generation_sequential_per_pop_and_readonly(): void
    {
        $this->popA->update(['last_customer_number' => 10]);

        $res1 = $this->actingAs($this->superAdmin)->postJson(route('customers.store'), [
            'pop_id'         => $this->popA->id,
            'name'           => 'Pelanggan Baru 1',
            'phone'          => '628123456780',
            'address'        => 'Jl. Kenanga 1',
            'subscribed_at'  => '2026-01-01',
            'package'        => '20 Mbps',
            'billing_type'   => 'Bulanan',
            'service_status' => 'ACTIVE',
        ]);
        $res1->assertStatus(201);
        $this->assertEquals('JCN-KTU-0011', $res1->json('data.customer_code'));

        $this->popA->refresh();
        $this->assertEquals(11, $this->popA->last_customer_number);

        // Create a second customer in Pop A: must be 0012 without duplication
        $res2 = $this->actingAs($this->superAdmin)->postJson(route('customers.store'), [
            'pop_id'         => $this->popA->id,
            'name'           => 'Pelanggan Baru 2',
            'phone'          => '628123456781',
            'address'        => 'Jl. Kenanga 2',
            'subscribed_at'  => '2026-01-01',
            'package'        => '20 Mbps',
            'billing_type'   => 'Bulanan',
            'service_status' => 'ACTIVE',
        ]);
        $res2->assertStatus(201);
        $this->assertEquals('JCN-KTU-0012', $res2->json('data.customer_code'));

        $this->popA->refresh();
        $this->assertEquals(12, $this->popA->last_customer_number);

        $customer = Customer::where('name', 'Pelanggan Baru 1')->firstOrFail();

        // Customer code is read-only on update
        $resUpdate = $this->actingAs($this->superAdmin)->putJson(route('customers.update', $customer), [
            'customer_code'  => 'JCN-KTU-9999',
            'name'           => 'Pelanggan Baru 1 Diubah',
            'phone'          => '628123456780',
            'address'        => 'Jl. Kenanga 1',
            'subscribed_at'  => '2026-01-01',
            'package'        => '20 Mbps',
            'billing_type'   => 'Bulanan',
            'service_status' => 'ACTIVE',
        ]);
        $resUpdate->assertStatus(200);

        $customer->refresh();
        $this->assertEquals('JCN-KTU-0011', $customer->customer_code);
        $this->assertEquals('Pelanggan Baru 1 Diubah', $customer->name);
    }

    public function test_import_row_rejected_if_pop_mismatches_admin_pop_and_syncs_last_number(): void
    {
        Storage::fake('local');

        // 1. Admin A attempts to import row for POP EP -> Rejected
        $csvMismatched = implode("\n", [
            'ID Pelanggan,Nama,Nomor Telepon,Alamat,Tanggal Mulai Berlangganan,Jenis Tagihan,Paket,Status Layanan,Periode,Nominal Ditangguhkan,Status',
            'JCN-EP-0005,Budi Salah POP,081234567890,Jl. Melati No. 1,2026-01-01,Pascabayar,Paket 20 Mbps,ACTIVE,2026-07,150000,UNPAID',
        ]);
        $path1 = 'imports/test_mismatch.csv';
        Storage::put($path1, $csvMismatched);

        $batch1 = ImportBatch::create([
            'original_name'    => 'test_mismatch.csv',
            'file_path'        => $path1,
            'total_rows'       => 1,
            'duplicate_policy' => 'skip',
            'status'           => 'queued',
            'user_id'          => $this->adminA->id,
            'pop_id'           => $this->adminA->pop_id,
        ]);

        (new ProcessImport($batch1))->handle();
        $batch1->refresh();
        $this->assertEquals(0, $batch1->created_count);
        $this->assertEquals(1, $batch1->error_count);

        // 2. Super admin imports row JCN-KTU-0085 -> succeeds and updates last_customer_number
        $this->popA->update(['last_customer_number' => 10]);

        $csvValid = implode("\n", [
            'ID Pelanggan,Nama,Nomor Telepon,Alamat,Tanggal Mulai Berlangganan,Jenis Tagihan,Paket,Status Layanan,Periode,Nominal Ditangguhkan,Status',
            'JCN-KTU-0085,Budi Valid,081234567892,Jl. Melati No. 2,2026-01-01,Pascabayar,Paket 20 Mbps,ACTIVE,2026-07,150000,UNPAID',
        ]);
        $path2 = 'imports/test_valid.csv';
        Storage::put($path2, $csvValid);

        $batch2 = ImportBatch::create([
            'original_name'    => 'test_valid.csv',
            'file_path'        => $path2,
            'total_rows'       => 1,
            'duplicate_policy' => 'skip',
            'status'           => 'queued',
            'user_id'          => $this->superAdmin->id,
        ]);

        (new ProcessImport($batch2))->handle();
        $batch2->refresh();
        $this->assertEquals(1, $batch2->created_count);

        $this->popA->refresh();
        $this->assertEquals(85, $this->popA->last_customer_number);
    }

    public function test_set_password_token_lifecycle_and_validation(): void
    {
        $staff = User::factory()->admin($this->popA)->create([
            'password' => Hash::make('initial-random-hash'),
        ]);

        $token = Password::broker()->createToken($staff);

        // Public page renders
        $this->get(route('password.set', ['token' => $token, 'email' => $staff->email]))
            ->assertStatus(200);

        // Validation: too short (< 10) or only letters
        $this->post(route('password.set.post'), [
            'token'                 => $token,
            'email'                 => $staff->email,
            'password'              => 'short1',
            'password_confirmation' => 'short1',
        ])->assertSessionHasErrors('password');

        $this->post(route('password.set.post'), [
            'token'                 => $token,
            'email'                 => $staff->email,
            'password'              => 'onlyletterswithoutdigits',
            'password_confirmation' => 'onlyletterswithoutdigits',
        ])->assertSessionHasErrors('password');

        // Valid password: min 10 chars, letters and numbers
        $res = $this->post(route('password.set.post'), [
            'token'                 => $token,
            'email'                 => $staff->email,
            'password'              => 'PasswordKuat123!',
            'password_confirmation' => 'PasswordKuat123!',
        ]);
        $res->assertRedirect(route('login'));
        $res->assertSessionHas('success');

        // Password hash updated
        $staff->refresh();
        $this->assertTrue(Hash::check('PasswordKuat123!', $staff->password));

        // Token cannot be reused
        $this->post(route('password.set.post'), [
            'token'                 => $token,
            'email'                 => $staff->email,
            'password'              => 'PasswordBaruLagi456!',
            'password_confirmation' => 'PasswordBaruLagi456!',
        ])->assertSessionHasErrors('email');
    }

    public function test_set_password_token_expires_after_24_hours(): void
    {
        $staff = User::factory()->admin($this->popA)->create();
        $token = Password::broker()->createToken($staff);

        // Advance time 25 hours (expiration is 24 hours = 1440 minutes)
        $this->travel(25)->hours();

        $res = $this->post(route('password.set.post'), [
            'token'                 => $token,
            'email'                 => $staff->email,
            'password'              => 'PasswordBaru123!',
            'password_confirmation' => 'PasswordBaru123!',
        ]);

        $res->assertSessionHasErrors('email');
    }

    public function test_inactive_user_cannot_login_and_active_session_is_terminated_when_deactivated(): void
    {
        $inactiveUser = User::factory()->admin($this->popA)->create([
            'is_active' => false,
            'password'  => Hash::make('secretPassword123'),
        ]);

        // Login fails for inactive user
        $this->post('/login', [
            'email'    => $inactiveUser->email,
            'password' => 'secretPassword123',
        ])->assertSessionHasErrors('email');

        // Active user logs in, then is deactivated
        $activeUser = User::factory()->admin($this->popA)->create([
            'is_active' => true,
        ]);

        $this->actingAs($activeUser)->get(route('customers.index'))->assertStatus(200);

        // Deactivate user in DB
        $activeUser->update(['is_active' => false]);

        // Next web request gets redirected to login, json request gets 403
        $this->actingAs($activeUser)->get(route('customers.index'))->assertRedirect(route('login'));
        $this->actingAs($activeUser)->getJson(route('customers.index'))->assertStatus(403);
    }

    public function test_last_super_admin_cannot_be_deactivated_or_demoted_and_cannot_change_own_status(): void
    {
        // $this->superAdmin is the only super admin
        $this->assertEquals(1, User::where('role', Role::SUPER_ADMIN)->count());

        // Attempting to demote sole super admin -> 422
        $resDemote = $this->actingAs($this->superAdmin)->putJson(route('staff.update', $this->superAdmin), [
            'name'      => $this->superAdmin->name,
            'email'     => $this->superAdmin->email,
            'role'      => Role::ADMIN->value,
            'pop_id'    => $this->popA->id,
            'is_active' => 1,
        ]);
        $resDemote->assertStatus(422);

        // Attempting to deactivate sole super admin -> 422
        $resDeactivate = $this->actingAs($this->superAdmin)->putJson(route('staff.update', $this->superAdmin), [
            'name'      => $this->superAdmin->name,
            'email'     => $this->superAdmin->email,
            'role'      => Role::SUPER_ADMIN->value,
            'is_active' => 0,
        ]);
        $resDeactivate->assertStatus(422);

        // Even with a second super admin, cannot demote/deactivate SELF
        $secondSuper = User::factory()->superAdmin()->create();
        $resSelfDeact = $this->actingAs($this->superAdmin)->putJson(route('staff.update', $this->superAdmin), [
            'name'      => $this->superAdmin->name,
            'email'     => $this->superAdmin->email,
            'role'      => Role::SUPER_ADMIN->value,
            'is_active' => 0,
        ]);
        $resSelfDeact->assertStatus(422);
    }

    public function test_staff_update_variations_changing_only_phone_number_succeeds(): void
    {
        // (a) Variation 1: Sole super_admin updates own profile changing only phone (without role, without is_active, as sent by browser when controls are disabled)
        $res1 = $this->actingAs($this->superAdmin)->putJson(route('staff.update', $this->superAdmin), [
            'name'  => $this->superAdmin->name,
            'email' => $this->superAdmin->email,
            'phone' => '081234567890',
        ]);
        $res1->assertStatus(200);
        $this->superAdmin->refresh();
        $this->assertEquals('6281234567890', $this->superAdmin->phone);
        $this->assertTrue($this->superAdmin->is_active);
        $this->assertEquals(Role::SUPER_ADMIN, $this->superAdmin->role);

        // Variation 2: Update staff without role, with explicit is_active=true
        $staff = User::factory()->admin($this->popA)->create(['phone' => '628111111111']);
        $res2 = $this->actingAs($this->superAdmin)->putJson(route('staff.update', $staff), [
            'name'      => $staff->name,
            'email'     => $staff->email,
            'phone'     => '082222222222',
            'is_active' => true,
        ]);
        $res2->assertStatus(200);
        $staff->refresh();
        $this->assertEquals('6282222222222', $staff->phone);
        $this->assertEquals(Role::ADMIN, $staff->role);
        $this->assertTrue($staff->is_active);

        // Variation 3: Update staff with role, without is_active
        $res3 = $this->actingAs($this->superAdmin)->putJson(route('staff.update', $staff), [
            'name'   => $staff->name,
            'email'  => $staff->email,
            'phone'  => '083333333333',
            'role'   => Role::ADMIN->value,
            'pop_id' => $this->popA->id,
        ]);
        $res3->assertStatus(200);
        $staff->refresh();
        $this->assertEquals('6283333333333', $staff->phone);
        $this->assertTrue($staff->is_active);
    }

    public function test_staff_creation_and_reset_access_returns_single_use_link(): void
    {
        // 1. Create staff returns set_password_url
        $createRes = $this->actingAs($this->superAdmin)->postJson(route('staff.store'), [
            'name'      => 'Staf Tautan Baru',
            'email'     => 'tautanbaru@jcn.net.id',
            'role'      => Role::ADMIN->value,
            'pop_id'    => $this->popA->id,
            'is_active' => 1,
        ]);
        $createRes->assertStatus(201);
        $setUrl = $createRes->json('set_password_url');
        $this->assertNotEmpty($setUrl);
        $this->assertStringContainsString('/set-password/', $setUrl);

        // Parse token and email from URL
        parse_str(parse_url($setUrl, PHP_URL_QUERY), $queryParams);
        preg_match('#/set-password/([a-f0-9]+)#', $setUrl, $matches);
        $token = $matches[1] ?? '';
        $email = $queryParams['email'] ?? '';

        // Use the link once to set password (as guest)
        auth()->logout();
        $this->post(route('password.set.post'), [
            'token'                 => $token,
            'email'                 => $email,
            'password'              => 'PasswordBaru99!',
            'password_confirmation' => 'PasswordBaru99!',
        ])->assertRedirect(route('login'));

        // Reuse fails
        $this->post(route('password.set.post'), [
            'token'                 => $token,
            'email'                 => $email,
            'password'              => 'PasswordLagi99!',
            'password_confirmation' => 'PasswordLagi99!',
        ])->assertSessionHasErrors('email');

        // 2. Reset access returns a new set_password_url
        $createdUser = User::where('email', 'tautanbaru@jcn.net.id')->firstOrFail();
        $resetRes = $this->actingAs($this->superAdmin)->postJson(route('staff.reset-access', $createdUser));
        $resetRes->assertStatus(200);
        $resetUrl = $resetRes->json('set_password_url');
        $this->assertNotEmpty($resetUrl);
        $this->assertNotEquals($setUrl, $resetUrl);
    }

    public function test_staff_update_by_non_super_admin_is_forbidden(): void
    {
        $staff = User::factory()->admin($this->popA)->create();

        // Non-super-admin (Admin KTU) attempting staff update -> 403
        $this->actingAs($this->adminA)->putJson(route('staff.update', $staff), [
            'name'  => 'Hacked by Admin A',
            'email' => $staff->email,
        ])->assertStatus(403);
    }

    public function test_set_password_link_uses_app_url_even_when_request_comes_from_different_host(): void
    {
        config(['app.url' => 'https://contoh.test']);

        // Request datang dari host lain (mis. http://different-host.com)
        $createRes = $this->actingAs($this->superAdmin)
            ->withServerVariables(['HTTP_HOST' => 'different-host.com', 'HTTPS' => 'off'])
            ->postJson('http://different-host.com/staff', [
                'name'      => 'Staf Host Uji',
                'email'     => 'hostuji@jcn.net.id',
                'role'      => Role::ADMIN->value,
                'pop_id'    => $this->popA->id,
                'is_active' => 1,
            ]);

        $createRes->assertStatus(201);
        $setUrl = $createRes->json('set_password_url');
        $this->assertStringStartsWith('https://contoh.test/set-password/', $setUrl);

        // Reset akses juga selalu menghasilkan tautan berawalan config('app.url')
        $user = User::where('email', 'hostuji@jcn.net.id')->firstOrFail();
        $resetRes = $this->actingAs($this->superAdmin)
            ->withServerVariables(['HTTP_HOST' => 'different-host.com', 'HTTPS' => 'off'])
            ->postJson("http://different-host.com/staff/{$user->id}/reset-access");

        $resetRes->assertStatus(200);
        $resetUrl = $resetRes->json('set_password_url');
        $this->assertStringStartsWith('https://contoh.test/set-password/', $resetUrl);

        // Token tetap sekali pakai
        parse_str(parse_url($resetUrl, PHP_URL_QUERY), $queryParams);
        preg_match('#/set-password/([a-f0-9]+)#', $resetUrl, $matches);
        $token = $matches[1] ?? '';
        $email = $queryParams['email'] ?? '';

        auth()->logout();
        $this->post(route('password.set.post'), [
            'token'                 => $token,
            'email'                 => $email,
            'password'              => 'PasswordBaru99!',
            'password_confirmation' => 'PasswordBaru99!',
        ])->assertRedirect(route('login'));

        // Penggunaan kedua kali ditolak
        $this->post(route('password.set.post'), [
            'token'                 => $token,
            'email'                 => $email,
            'password'              => 'PasswordLagi99!',
            'password_confirmation' => 'PasswordLagi99!',
        ])->assertSessionHasErrors('email');
    }

    public function test_activity_logs_recorded_without_sensitive_data(): void
    {
        // 1. Staff creation
        $this->actingAs($this->superAdmin)->postJson(route('staff.store'), [
            'name'      => 'Staf Baru Log',
            'email'     => 'staflog@netbill.test',
            'role'      => Role::ADMIN->value,
            'pop_id'    => $this->popA->id,
            'is_active' => 1,
        ])->assertStatus(201);

        // 2. Customer creation
        $customerRes = $this->actingAs($this->superAdmin)->postJson(route('customers.store'), [
            'pop_id'         => $this->popA->id,
            'name'           => 'Pelanggan Log',
            'phone'          => '628123456788',
            'address'        => 'Jl. Log 1',
            'subscribed_at'  => '2026-01-01',
            'package'        => '10 Mbps',
            'billing_type'   => 'Bulanan',
            'service_status' => 'ACTIVE',
        ]);
        $customerRes->assertStatus(201);
        $customer = Customer::where('name', 'Pelanggan Log')->firstOrFail();

        // 3. Generate activation code
        $this->actingAs($this->superAdmin)->postJson(route('customers.activation.generate', $customer))
            ->assertStatus(200);

        // Verify logs
        $logs = ActivityLog::all();
        $this->assertNotEmpty($logs);

        foreach ($logs as $log) {
            $desc = $log->description;
            // No passwords, tokens, activation codes in descriptions
            $this->assertStringNotContainsString('password', strtolower($desc));
            $this->assertStringNotContainsString('secret', strtolower($desc));
            $this->assertDoesNotMatchRegularExpression('/\b\d{4}-\d{4}\b/', $desc); // formatted activation code format
        }
    }

    public function test_customer_api_works_with_jcn_code_trimmed_and_case_insensitive(): void
    {
        $customer = Customer::factory()->create([
            'pop_id'        => $this->popA->id,
            'customer_code' => 'JCN-KTU-0077',
            'password'      => Hash::make('password123'),
            'activated_at'  => now(),
        ]);

        // Login with whitespace and lowercase
        $res = $this->postJson('/api/v1/auth/login', [
            'customer_code' => '  jcn-ktu-0077  ',
            'password'      => 'password123',
            'device_name'   => 'Android App',
        ]);

        $res->assertStatus(200);
        $res->assertJsonStructure(['data' => ['token', 'profile']]);
    }
}

