<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\MarketingFee;
use App\Models\MarketingProfile;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MarketingFeeAndPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $popA;
    protected Pop $popB;
    protected User $superAdmin;
    protected User $adminPopA;
    protected User $adminPopB;
    protected User $marketingA;
    protected User $marketingB;
    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->popA = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Kotabaru']);
        $this->popB = Pop::factory()->create(['code' => 'EP', 'name' => 'POP Enam Puluh']);

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

        $this->marketingA = User::factory()->create([
            'role' => Role::MARKETING,
            'pop_id' => $this->popA->id,
            'is_active' => true,
        ]);

        $this->marketingB = User::factory()->create([
            'role' => Role::MARKETING,
            'pop_id' => $this->popB->id,
            'is_active' => true,
        ]);

        $this->technician = User::factory()->create([
            'role' => Role::TECHNICIAN,
            'pop_id' => $this->popA->id,
            'is_active' => true,
        ]);

        // Set default fee in settings
        DB::table('settings')->updateOrInsert(
            ['key' => 'marketing_fee_default'],
            ['value' => 75000, 'updated_at' => now()]
        );
    }

    public function test_fee_generated_once_on_first_activation_of_referred_customer_and_not_for_admin_or_import(): void
    {
        // 1. Customer with referral initially PENDING
        $referredCustomer = Customer::factory()->create([
            'pop_id'              => $this->popA->id,
            'registered_via'      => 'marketing',
            'referred_by_user_id' => $this->marketingA->id,
            'service_status'      => 'PENDING',
        ]);

        $this->assertDatabaseMissing('marketing_fees', ['customer_id' => $referredCustomer->id]);

        // Activate customer for the first time
        $referredCustomer->service_status = 'ACTIVE';
        $referredCustomer->save();

        $this->assertDatabaseHas('marketing_fees', [
            'customer_id'       => $referredCustomer->id,
            'marketing_user_id' => $this->marketingA->id,
            'pop_id'            => $this->popA->id,
            'amount'            => 75000,
            'status'            => 'PENDING',
        ]);

        $feeCount = MarketingFee::where('customer_id', $referredCustomer->id)->count();
        $this->assertEquals(1, $feeCount);

        // Transition: ACTIVE -> SUSPENDED -> ACTIVE again (MUST NOT create a second fee)
        $referredCustomer->service_status = 'SUSPENDED';
        $referredCustomer->save();

        $referredCustomer->service_status = 'ACTIVE';
        $referredCustomer->save();

        $this->assertEquals(1, MarketingFee::where('customer_id', $referredCustomer->id)->count());

        // 2. Customer created by admin (no referral) activated -> NO fee
        $adminCustomer = Customer::factory()->create([
            'pop_id'              => $this->popA->id,
            'registered_via'      => 'admin',
            'referred_by_user_id' => null,
            'service_status'      => 'PENDING',
        ]);
        $adminCustomer->service_status = 'ACTIVE';
        $adminCustomer->save();
        $this->assertDatabaseMissing('marketing_fees', ['customer_id' => $adminCustomer->id]);

        // 3. Customer from import (no referral) activated -> NO fee
        $importCustomer = Customer::factory()->create([
            'pop_id'              => $this->popA->id,
            'registered_via'      => 'import',
            'referred_by_user_id' => null,
            'service_status'      => 'PENDING',
        ]);
        $importCustomer->service_status = 'ACTIVE';
        $importCustomer->save();
        $this->assertDatabaseMissing('marketing_fees', ['customer_id' => $importCustomer->id]);
    }

    public function test_account_number_encrypted_in_db_and_not_leaked_in_logs_or_unauthorized_responses(): void
    {
        $rawSecretAccount = '987654321098';

        $res = $this->actingAs($this->adminPopA)->putJson(route('marketing-profiles.update', $this->marketingA), [
            'payout_method'  => 'BANK',
            'provider_name'  => 'BCA',
            'account_number' => $rawSecretAccount,
            'account_holder' => 'Budi Santoso Marketing',
            'notes'          => 'Cabang Pekanbaru',
        ]);

        $res->assertStatus(200);

        // 1. Assert raw value in DB is encrypted and not equal to plaintext
        $rawDbValue = DB::table('marketing_profiles')
            ->where('user_id', $this->marketingA->id)
            ->value('account_number');

        $this->assertNotNull($rawDbValue);
        $this->assertNotEquals($rawSecretAccount, $rawDbValue);
        $this->assertStringNotContainsString($rawSecretAccount, $rawDbValue);

        // 2. Eloquent model decrypts on access
        $profile = MarketingProfile::where('user_id', $this->marketingA->id)->firstOrFail();
        $this->assertEquals($rawSecretAccount, $profile->account_number);
        $this->assertStringEndsWith('1098', $profile->maskedAccountNumber());
        $this->assertStringStartsWith('••••', $profile->maskedAccountNumber());

        // 3. Activity logs do NOT contain the secret account number
        $recentLogs = DB::table('activity_logs')->orderByDesc('id')->take(10)->pluck('description')->implode(' ');
        $this->assertStringNotContainsString($rawSecretAccount, $recentLogs);

        // 4. DataTables JSON list shows masked account number
        $listRes = $this->actingAs($this->adminPopA)->getJson(route('marketing-profiles.index'));
        $listRes->assertStatus(200)
            ->assertJsonFragment(['masked_account' => $profile->maskedAccountNumber()])
            ->assertJsonMissing(['masked_account' => $rawSecretAccount]);
    }

    public function test_pop_isolation_on_profiles_fees_and_exports(): void
    {
        $profileB = MarketingProfile::factory()->create([
            'user_id'        => $this->marketingB->id,
            'account_number' => '1122334455',
            'account_holder' => 'Marketing B Rahasia',
        ]);

        $custB = Customer::factory()->create(['pop_id' => $this->popB->id, 'service_status' => 'PENDING', 'referred_by_user_id' => $this->marketingB->id]);
        $custB->service_status = 'ACTIVE';
        $custB->save();

        $feeB = MarketingFee::where('customer_id', $custB->id)->firstOrFail();

        // 1. Admin POP A cannot view or edit Marketing B profile -> 404
        $this->actingAs($this->adminPopA)->getJson(route('marketing-profiles.show', $this->marketingB))->assertStatus(404);
        $this->actingAs($this->adminPopA)->putJson(route('marketing-profiles.update', $this->marketingB), [
            'payout_method'  => 'BANK',
            'provider_name'  => 'Mandiri',
            'account_number' => '5544332211',
            'account_holder' => 'Hacked',
        ])->assertStatus(404);

        // 2. Admin POP A cannot edit amount of Fee POP B -> 404
        $this->actingAs($this->adminPopA)->putJson(route('marketing-fees.update-amount', $feeB), [
            'amount' => 100000,
        ])->assertStatus(404);

        // 3. Admin POP A export does not contain POP B data
        $exportRes = $this->actingAs($this->adminPopA)->get(route('export.marketing-fees', ['month' => now()->format('Y-m')]));
        $exportRes->assertStatus(200);

        // Save content to temp file and parse with PhpSpreadsheet
        $tempFile = tempnam(sys_get_temp_dir(), 'export_test_');
        file_put_contents($tempFile, $exportRes->streamedContent());

        $spreadsheet = IOFactory::load($tempFile);
        $sheet = $spreadsheet->getSheetByName('Rincian');
        $sheetData = $sheet->toArray();
        unlink($tempFile);

        $flatData = json_encode($sheetData);
        $this->assertStringNotContainsString('Marketing B Rahasia', $flatData);
        $this->assertStringNotContainsString('1122334455', $flatData);
    }

    public function test_bulk_pay_marks_selected_fees_as_paid_with_date_and_note(): void
    {
        $cust1 = Customer::factory()->create(['pop_id' => $this->popA->id, 'referred_by_user_id' => $this->marketingA->id, 'service_status' => 'ACTIVE']);
        $cust2 = Customer::factory()->create(['pop_id' => $this->popA->id, 'referred_by_user_id' => $this->marketingA->id, 'service_status' => 'ACTIVE']);
        $cust3 = Customer::factory()->create(['pop_id' => $this->popA->id, 'referred_by_user_id' => $this->marketingA->id, 'service_status' => 'ACTIVE']);

        $fee1 = MarketingFee::where('customer_id', $cust1->id)->firstOrFail();
        $fee2 = MarketingFee::where('customer_id', $cust2->id)->firstOrFail();
        $fee3 = MarketingFee::where('customer_id', $cust3->id)->firstOrFail();

        $res = $this->actingAs($this->adminPopA)->postJson(route('marketing-fees.bulk-pay'), [
            'fee_ids'   => [$fee1->id, $fee2->id],
            'paid_at'   => '2026-11-20',
            'paid_note' => 'Transfer Payroll Batch 1',
        ]);

        $res->assertStatus(200)->assertJson(['success' => true]);

        $fee1->refresh();
        $fee2->refresh();
        $fee3->refresh();

        $this->assertEquals('PAID', $fee1->status);
        $this->assertEquals('2026-11-20', $fee1->paid_at->format('Y-m-d'));
        $this->assertEquals('Transfer Payroll Batch 1', $fee1->paid_note);

        $this->assertEquals('PAID', $fee2->status);
        $this->assertEquals('PENDING', $fee3->status);
        $this->assertNull($fee3->paid_at);
    }

    public function test_unpay_fee_is_restricted_to_super_admin(): void
    {
        $cust = Customer::factory()->create(['pop_id' => $this->popA->id, 'referred_by_user_id' => $this->marketingA->id, 'service_status' => 'ACTIVE']);
        $fee = MarketingFee::where('customer_id', $cust->id)->firstOrFail();

        $fee->update([
            'status'    => 'PAID',
            'paid_at'   => now(),
            'paid_note' => 'Dibayar',
        ]);

        // Admin POP A cannot unpay -> 403
        $this->actingAs($this->adminPopA)->postJson(route('marketing-fees.unpay', $fee))->assertStatus(403);

        // Super Admin can unpay -> 200
        $this->actingAs($this->superAdmin)->postJson(route('marketing-fees.unpay', $fee))->assertStatus(200);

        $fee->refresh();
        $this->assertEquals('PENDING', $fee->status);
        $this->assertNull($fee->paid_at);
        $this->assertNull($fee->paid_note);
    }

    public function test_export_contains_correct_sheets_data_text_account_and_formula_sanitization(): void
    {
        // Marketing profile with potentially malicious formula prefix
        MarketingProfile::factory()->create([
            'user_id'        => $this->marketingA->id,
            'payout_method'  => 'BANK',
            'provider_name'  => 'BCA',
            'account_number' => '+6281234567890',
            'account_holder' => '=cmd|"/C calc"!A0',
        ]);

        $cust = Customer::factory()->create(['pop_id' => $this->popA->id, 'referred_by_user_id' => $this->marketingA->id, 'service_status' => 'ACTIVE']);
        $month = now()->format('Y-m');

        $res = $this->actingAs($this->adminPopA)->get(route('export.marketing-fees', ['month' => $month]));
        $res->assertStatus(200);

        $tempFile = tempnam(sys_get_temp_dir(), 'export_test2_');
        file_put_contents($tempFile, $res->streamedContent());

        $spreadsheet = IOFactory::load($tempFile);
        $this->assertCount(2, $spreadsheet->getSheetNames());
        $this->assertEquals('Rincian', $spreadsheet->getSheetNames()[0]);
        $this->assertEquals('Ringkasan', $spreadsheet->getSheetNames()[1]);

        $sheetDetail = $spreadsheet->getSheetByName('Rincian');

        // Check account number (column K) and account holder (column L)
        $accountNumCell = $sheetDetail->getCell('K2')->getValue();
        $accountHolderCell = $sheetDetail->getCell('L2')->getValue();

        // Formula injection neutralized with leading apostrophe
        $this->assertStringStartsWith("'", $accountHolderCell);
        $this->assertStringStartsWith("'", $accountNumCell);

        unlink($tempFile);
    }

    public function test_access_control_marketing_and_other_roles_cannot_access_fees_or_profiles(): void
    {
        // Marketing cannot access fee list or profile management
        $this->actingAs($this->marketingA)->get(route('marketing-fees.index'))->assertStatus(403);
        $this->actingAs($this->marketingA)->get(route('marketing-profiles.index'))->assertStatus(403);
        $this->actingAs($this->marketingA)->get(route('export.marketing-fees', ['month' => '2026-11']))->assertStatus(403);

        // Technician cannot access
        $this->actingAs($this->technician)->get(route('marketing-fees.index'))->assertStatus(403);
        $this->actingAs($this->technician)->get(route('marketing-profiles.index'))->assertStatus(403);
        $this->actingAs($this->technician)->get(route('export.marketing-fees', ['month' => '2026-11']))->assertStatus(403);
    }
}
