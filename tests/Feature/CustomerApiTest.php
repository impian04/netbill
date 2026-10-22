<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\ActivationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIVATION_ERROR = 'Kode tidak valid atau sudah tidak berlaku. Hubungi admin untuk kode baru.';

    // ─────────────────────────────────────────────────────────────
    // Area 1: Aktivasi
    // ─────────────────────────────────────────────────────────────

    public function test_activation_successful(): void
    {
        $customer = Customer::factory()->create([
            'phone'          => '628123456789',
            'service_status' => 'ACTIVE',
        ]);
        $plainCode = ActivationCodeService::generate($customer);

        $response = $this->postJson('/api/v1/auth/activate', [
            'customer_code'         => $customer->customer_code,
            'phone'                 => '08123456789',
            'activation_code'       => ActivationCodeService::format($plainCode),
            'password'              => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'device_name'           => 'Redmi Note 12',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'profile' => ['customer_code', 'name', 'phone']]]);

        $customer->refresh();
        $this->assertNotNull($customer->activated_at);
        $this->assertNotNull($customer->password);
        $this->assertTrue(Hash::check('Rahasia123!', $customer->password));
        $this->assertNull($customer->activation_code_hash);
    }

    public function test_activation_code_is_single_use(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '628123456789',
        ]);
        $plainCode = ActivationCodeService::generate($customer);

        // First activation (success)
        $this->postJson('/api/v1/auth/activate', [
            'customer_code'         => $customer->customer_code,
            'phone'                 => '628123456789',
            'activation_code'       => $plainCode,
            'password'              => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'device_name'           => 'Phone1',
        ])->assertStatus(200);

        // Second activation with the same code must return generic 422 error
        $second = $this->postJson('/api/v1/auth/activate', [
            'customer_code'         => $customer->customer_code,
            'phone'                 => '628123456789',
            'activation_code'       => $plainCode,
            'password'              => 'RahasiaBaru123!',
            'password_confirmation' => 'RahasiaBaru123!',
            'device_name'           => 'Phone2',
        ]);

        $second->assertStatus(422)
            ->assertJson(['message' => self::ACTIVATION_ERROR]);
    }

    public function test_activation_code_expired_fails(): void
    {
        $customer = Customer::factory()->create([
            'phone'                 => '628123456789',
            'activation_code_hash'  => Hash::make('ABCDEFGH'),
            'activation_expires_at' => now()->subDay(),
            'activation_attempts'   => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/activate', [
            'customer_code'         => $customer->customer_code,
            'phone'                 => '628123456789',
            'activation_code'       => 'ABCDEFGH',
            'password'              => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'device_name'           => 'Phone1',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => self::ACTIVATION_ERROR]);
    }

    public function test_activation_temporarily_locked_after_five_failed_attempts(): void
    {
        $customer = Customer::factory()->create(['phone' => '628123456789']);
        $plainCode = ActivationCodeService::generate($customer);

        // 5 wrong attempts trigger temporary lock (15 minutes)
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/auth/activate', [
                'customer_code'         => $customer->customer_code,
                'phone'                 => '628123456789',
                'activation_code'       => 'WRONGCOD' . $i,
                'password'              => 'Rahasia123!',
                'password_confirmation' => 'Rahasia123!',
                'device_name'           => 'Phone1',
            ])->assertStatus(422)
                ->assertJson(['message' => self::ACTIVATION_ERROR]);
        }

        $customer->refresh();
        $this->assertEquals(5, $customer->activation_attempts);
        $this->assertNotNull($customer->activation_locked_until);
        $this->assertTrue($customer->activation_locked_until->isFuture());
        $this->assertEquals('locked_temporary', $customer->activationStatus());

        // Clear rate limiter cache so the 6th call tests the service temporary lock, not HTTP rate limit
        cache()->flush();

        // Even with correct code, attempt during lock window must fail with same generic error
        $response = $this->postJson('/api/v1/auth/activate', [
            'customer_code'         => $customer->customer_code,
            'phone'                 => '628123456789',
            'activation_code'       => $plainCode,
            'password'              => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'device_name'           => 'Phone1',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => self::ACTIVATION_ERROR]);
    }

    public function test_activation_permanently_locked_after_one_hundred_failed_attempts(): void
    {
        $customer = Customer::factory()->create(['phone' => '628123456789']);
        $plainCode = ActivationCodeService::generate($customer);

        // Simulate reaching 100 failed attempts (permanent lock until admin regenerates)
        $customer->forceFill([
            'activation_attempts'     => ActivationCodeService::MAX_ATTEMPTS,
            'activation_locked_until' => null, // Lock window expired but MAX_ATTEMPTS reached
        ])->save();

        $this->assertEquals('locked_permanent', $customer->activationStatus());

        // Attempt with correct code must fail
        $response = $this->postJson('/api/v1/auth/activate', [
            'customer_code'         => $customer->customer_code,
            'phone'                 => '628123456789',
            'activation_code'       => $plainCode,
            'password'              => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'device_name'           => 'Phone1',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => self::ACTIVATION_ERROR]);
    }

    public function test_activation_returns_generic_error_on_mismatched_phone_or_customer(): void
    {
        $customer = Customer::factory()->create(['phone' => '628123456789']);
        $plainCode = ActivationCodeService::generate($customer);

        // Mismatched phone
        $response = $this->postJson('/api/v1/auth/activate', [
            'customer_code'         => $customer->customer_code,
            'phone'                 => '628999999999',
            'activation_code'       => $plainCode,
            'password'              => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'device_name'           => 'Phone1',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => self::ACTIVATION_ERROR]);

        // Non-existent customer code
        $resUnknown = $this->postJson('/api/v1/auth/activate', [
            'customer_code'         => 'NONEXISTENT',
            'phone'                 => '628123456789',
            'activation_code'       => $plainCode,
            'password'              => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'device_name'           => 'Phone1',
        ]);

        $resUnknown->assertStatus(422)
            ->assertJson(['message' => self::ACTIVATION_ERROR]);
    }

    // ─────────────────────────────────────────────────────────────
    // Area 2: Login & Multi-Device Tokens
    // ─────────────────────────────────────────────────────────────

    public function test_login_fails_with_wrong_password(): void
    {
        $customer = Customer::factory()->create([
            'password'     => Hash::make('Rahasia123!'),
            'activated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'customer_code' => $customer->customer_code,
            'password'      => 'WrongPassword!',
            'device_name'   => 'Phone1',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Kode pelanggan atau password salah.']);
    }

    public function test_login_inactive_returns_401_on_wrong_password_and_403_on_correct_password(): void
    {
        $customer = Customer::factory()->create([
            'password'       => Hash::make('Rahasia123!'),
            'activated_at'   => now(),
            'service_status' => 'INACTIVE',
        ]);

        // Wrong password on INACTIVE account MUST still return generic 401 (not revealing account state)
        $wrongPass = $this->postJson('/api/v1/auth/login', [
            'customer_code' => $customer->customer_code,
            'password'      => 'WrongPassword!',
            'device_name'   => 'Phone1',
        ]);

        $wrongPass->assertStatus(401)
            ->assertJson(['message' => 'Kode pelanggan atau password salah.']);

        // Correct password on INACTIVE account returns 403
        $correctPass = $this->postJson('/api/v1/auth/login', [
            'customer_code' => $customer->customer_code,
            'password'      => 'Rahasia123!',
            'device_name'   => 'Phone1',
        ]);

        $correctPass->assertStatus(403)
            ->assertJson(['message' => 'Akun Anda tidak aktif. Hubungi layanan pelanggan.']);
    }

    public function test_existing_token_rejected_with_403_when_customer_becomes_inactive(): void
    {
        $customer = Customer::factory()->create([
            'password'       => Hash::make('Rahasia123!',),
            'activated_at'   => now(),
            'service_status' => 'ACTIVE',
        ]);

        $token = $customer->createToken('Device1', ['customer'])->plainTextToken;

        // Active customer can access /api/v1/me
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/me')
            ->assertStatus(200);

        // Admin changes status to INACTIVE
        $customer->update(['service_status' => 'INACTIVE']);

        // Forget auth guards so container resolves user freshly from DB
        $this->app['auth']->forgetGuards();

        // Next request with existing token MUST be rejected 403
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/me')
            ->assertStatus(403)
            ->assertJson(['message' => 'Akun Anda tidak aktif. Hubungi layanan pelanggan.']);
    }

    public function test_login_allowed_when_customer_is_suspended(): void
    {
        $customer = Customer::factory()->create([
            'password'       => Hash::make('Rahasia123!'),
            'activated_at'   => now(),
            'service_status' => 'SUSPENDED',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'customer_code' => $customer->customer_code,
            'password'      => 'Rahasia123!',
            'device_name'   => 'Phone1',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'profile']]);
    }

    public function test_login_allows_multi_device_up_to_five_active_tokens(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $customer = Customer::factory()->create([
            'customer_code' => 'MULTI-DEV',
            'password'      => Hash::make('Rahasia123!'),
            'activated_at'  => now(),
        ]);

        // Login 5 times on different devices
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'customer_code' => 'MULTI-DEV',
                'password'      => 'Rahasia123!',
                'device_name'   => 'Device ' . $i,
            ])->assertStatus(200);
        }

        $customer->refresh();
        $this->assertCount(5, $customer->tokens);
        $oldestTokenId = $customer->tokens()->orderBy('created_at')->first()->id;

        // Clear rate limiter so 6th login can proceed
        RateLimiter::clear('api_auth:code:MULTI-DEV');
        RateLimiter::clear('api_auth:ip:127.0.0.1');

        // 6th login: should prune oldest token and keep count at 5
        $this->postJson('/api/v1/auth/login', [
            'customer_code' => 'MULTI-DEV',
            'password'      => 'Rahasia123!',
            'device_name'   => 'Device 6',
        ])->assertStatus(200);

        $customer->refresh();
        $this->assertCount(5, $customer->tokens);
        $this->assertFalse($customer->tokens()->where('id', $oldestTokenId)->exists());
    }

    // ─────────────────────────────────────────────────────────────
    // Area 3: IDOR Protection & Invoice Items
    // ─────────────────────────────────────────────────────────────

    public function test_customer_cannot_view_another_customers_invoice(): void
    {
        $customerA = Customer::factory()->create(['password' => Hash::make('Pwd123!'), 'activated_at' => now()]);
        $customerB = Customer::factory()->create(['password' => Hash::make('Pwd123!'), 'activated_at' => now()]);

        $invoiceB = Invoice::factory()->create(['customer_id' => $customerB->id]);

        $tokenA = $customerA->createToken('DeviceA', ['customer'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson("/api/v1/invoices/{$invoiceB->id}");

        $response->assertStatus(404)
            ->assertJson(['message' => 'Data tidak ditemukan.']);
    }

    public function test_customer_invoice_list_only_contains_own_invoices_and_includes_customer_fields(): void
    {
        $customerA = Customer::factory()->create(['password' => Hash::make('Pwd123!'), 'activated_at' => now()]);
        $customerB = Customer::factory()->create(['password' => Hash::make('Pwd123!'), 'activated_at' => now()]);

        Invoice::factory()->create(['customer_id' => $customerA->id, 'period' => now()->startOfMonth()]);
        Invoice::factory()->create(['customer_id' => $customerA->id, 'period' => now()->subMonth()->startOfMonth()]);

        Invoice::factory()->create(['customer_id' => $customerB->id, 'period' => now()->startOfMonth()]);
        Invoice::factory()->create(['customer_id' => $customerB->id, 'period' => now()->subMonth()->startOfMonth()]);
        Invoice::factory()->create(['customer_id' => $customerB->id, 'period' => now()->subMonths(2)->startOfMonth()]);

        $tokenA = $customerA->createToken('DeviceA', ['customer'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        // Verify customer denormalized fields are present without N+1
        $firstItem = $response->json('data.0');
        $this->assertEquals($customerA->customer_code, $firstItem['customer_code']);
        $this->assertEquals($customerA->name, $firstItem['customer_name']);
        $this->assertEquals($customerA->address, $firstItem['address']);
        $this->assertEquals($customerA->package, $firstItem['package']);
    }

    // ─────────────────────────────────────────────────────────────
    // Area 4: Token isolasi guard
    // ─────────────────────────────────────────────────────────────

    public function test_customer_token_cannot_access_admin_routes(): void
    {
        $customer = Customer::factory()->create(['password' => Hash::make('Pwd123!'), 'activated_at' => now()]);
        $token = $customer->createToken('DeviceA', ['customer'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/customers');

        $response->assertRedirect('/login');
    }

    // ─────────────────────────────────────────────────────────────
    // Area 5: Regenerate activation code revokes tokens & clears password
    // ─────────────────────────────────────────────────────────────

    public function test_regenerating_activation_code_revokes_tokens_and_clears_password(): void
    {
        $customer = Customer::factory()->create([
            'password'     => Hash::make('OldPassword123!'),
            'activated_at' => now(),
        ]);
        $customer->createToken('OldDevice1', ['customer']);
        $customer->createToken('OldDevice2', ['customer']);

        $this->assertCount(2, $customer->tokens);
        $this->assertNotNull($customer->password);

        $plainCode = ActivationCodeService::generate($customer);

        $customer->refresh();
        $this->assertCount(0, $customer->tokens);
        $this->assertNull($customer->password);
        $this->assertNull($customer->activated_at);
        $this->assertNotNull($customer->activation_code_hash);
        $this->assertTrue(ActivationCodeService::verify($customer, $plainCode));
    }

    // ─────────────────────────────────────────────────────────────
    // Area 6: Ganti password menghapus token lain
    // ─────────────────────────────────────────────────────────────

    public function test_change_password_revokes_other_tokens(): void
    {
        $customer = Customer::factory()->create([
            'password'     => Hash::make('OldPassword123!'),
            'activated_at' => now(),
        ]);

        $token1 = $customer->createToken('Device1', ['customer'])->plainTextToken;
        $token2 = $customer->createToken('Device2', ['customer'])->plainTextToken;

        $this->assertCount(2, $customer->tokens);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson('/api/v1/me/password', [
                'current_password'      => 'OldPassword123!',
                'password'              => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password berhasil diperbarui.']);

        $customer->refresh();
        $this->assertCount(1, $customer->tokens);

        // Reset auth guards so container resolves the token fresh from database
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson('/api/v1/me')
            ->assertStatus(401);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/v1/me')
            ->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────
    // Area 7: Rate limit auth 429
    // ─────────────────────────────────────────────────────────────

    public function test_auth_rate_limiting(): void
    {
        RateLimiter::clear('api_auth:code:TEST-RATE');
        RateLimiter::clear('api_auth:ip:127.0.0.1');

        $customer = Customer::factory()->create([
            'customer_code' => 'TEST-RATE',
            'password'      => Hash::make('Password123!'),
            'activated_at'  => now(),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'customer_code' => 'TEST-RATE',
                'password'      => 'WrongPassword!',
                'device_name'   => 'RateTest',
            ])->assertStatus(401);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'customer_code' => 'TEST-RATE',
            'password'      => 'WrongPassword!',
            'device_name'   => 'RateTest',
        ]);

        $response->assertStatus(429)
            ->assertJson(['message' => 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.']);
    }

    // ─────────────────────────────────────────────────────────────
    // Area 8: Home & Support Envelope
    // ─────────────────────────────────────────────────────────────

    public function test_home_and_support_contacts_envelopes(): void
    {
        $customer = Customer::factory()->create(['password' => Hash::make('Pwd123!'), 'activated_at' => now()]);
        $token = $customer->createToken('DeviceA', ['customer'])->plainTextToken;

        // GET /home envelope
        $homeRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/home');

        $homeRes->assertStatus(200)
            ->assertJsonStructure(['data' => ['unpaid_count', 'total_unpaid_amount', 'latest_unpaid', 'announcements']]);

        // GET /support-contacts envelope & labels
        $supRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/support-contacts');

        $supRes->assertStatus(200)
            ->assertJsonStructure(['data' => [['category', 'label', 'phone']]]);

        $labels = collect($supRes->json('data'))->pluck('label')->all();
        $this->assertContains('Masalah Koneksi', $labels);
        $this->assertContains('Masalah Pembayaran', $labels);
        $this->assertContains('Bantuan Pemasangan/Pemasaran', $labels);
    }
}
