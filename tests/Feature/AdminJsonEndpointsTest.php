<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminJsonEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_admin_endpoints_require_authentication(): void
    {
        // Guest GET requests
        $this->getJson('/customers')->assertStatus(401);
        $this->getJson('/invoices')->assertStatus(401);
        $this->getJson('/announcements')->assertStatus(401);

        // Guest mutation requests
        $this->postJson('/customers', [])->assertStatus(401);
        $this->putJson('/customers/1', [])->assertStatus(401);
        $this->deleteJson('/customers/1')->assertStatus(401);
    }

    public function test_datatables_search_as_array_and_as_string(): void
    {
        $pop = \App\Models\Pop::factory()->create(['code' => 'KTU']);
        Customer::factory()->create(['name' => 'Ahmad Dahlan', 'customer_code' => 'JCN-KTU-0001', 'pop_id' => $pop->id]);
        Customer::factory()->create(['name' => 'Budi Santoso', 'customer_code' => 'JCN-KTU-0002', 'pop_id' => $pop->id]);

        // 1. Search sebagai array DataTables {value, regex}
        $resArray = $this->actingAs($this->admin)->getJson('/customers?' . http_build_query([
            'draw'   => 1,
            'search' => ['value' => 'Ahmad', 'regex' => 'false'],
        ]));

        $resArray->assertStatus(200)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Ahmad Dahlan');

        // 2. Search sebagai string sederhana
        $resString = $this->actingAs($this->admin)->getJson('/customers?' . http_build_query([
            'draw'   => 2,
            'search' => 'Budi',
        ]));

        $resString->assertStatus(200)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Budi Santoso');

        // 3. Search array kosong
        $resEmpty = $this->actingAs($this->admin)->getJson('/customers?' . http_build_query([
            'draw'   => 3,
            'search' => ['value' => '', 'regex' => 'false'],
        ]));

        $resEmpty->assertStatus(200)
            ->assertJsonPath('recordsFiltered', 2);
    }

    public function test_datatables_order_column_outside_whitelist_fallback_safely(): void
    {
        Customer::factory()->create();
        Invoice::factory()->create();
        Announcement::create(['title' => 'Tes', 'body' => 'Isi', 'is_active' => true]);

        // Customers: order index di luar whitelist
        $resCust = $this->actingAs($this->admin)->getJson('/customers?' . http_build_query([
            'draw'  => 1,
            'order' => [['column' => 999, 'dir' => 'desc']],
        ]));
        $resCust->assertStatus(200);

        // Invoices: order index di luar whitelist & direction invalid
        $resInv = $this->actingAs($this->admin)->getJson('/invoices?' . http_build_query([
            'draw'  => 1,
            'order' => [['column' => 999, 'dir' => 'invalid_dir']],
        ]));
        $resInv->assertStatus(200);

        // Announcements: order index di luar whitelist
        $resAnn = $this->actingAs($this->admin)->getJson('/announcements?' . http_build_query([
            'draw'  => 1,
            'order' => [['column' => 999, 'dir' => 'asc']],
        ]));
        $resAnn->assertStatus(200);
    }

    public function test_datatables_length_exceeding_100_is_capped(): void
    {
        Customer::factory()->count(105)->create();

        $response = $this->actingAs($this->admin)->getJson('/customers?' . http_build_query([
            'draw'   => 1,
            'length' => 500,
        ]));

        $response->assertStatus(200);
        $this->assertCount(100, $response->json('data'));
    }

    public function test_admin_endpoints_basic_filters(): void
    {
        // 1. Customer filter: package, service_status, billing_type
        Customer::factory()->create([
            'package'        => 'Paket 10 Mbps',
            'service_status' => 'ACTIVE',
            'billing_type'   => 'Prabayar',
        ]);
        Customer::factory()->create([
            'package'        => 'Paket 50 Mbps',
            'service_status' => 'SUSPENDED',
            'billing_type'   => 'Pascabayar',
        ]);

        $this->actingAs($this->admin)->getJson('/customers?package=Paket 10 Mbps')
            ->assertStatus(200)->assertJsonPath('recordsFiltered', 1);

        $this->actingAs($this->admin)->getJson('/customers?service_status=SUSPENDED')
            ->assertStatus(200)->assertJsonPath('recordsFiltered', 1);

        $this->actingAs($this->admin)->getJson('/customers?billing_type=Prabayar')
            ->assertStatus(200)->assertJsonPath('recordsFiltered', 1);

        // 2. Invoice filter: status, customer_id, period
        $c1 = Customer::factory()->create();
        $c2 = Customer::factory()->create();
        Invoice::factory()->create(['customer_id' => $c1->id, 'status' => 'PAID', 'period' => '2026-05-01']);
        Invoice::factory()->create(['customer_id' => $c2->id, 'status' => 'UNPAID', 'period' => '2026-06-01']);

        $this->actingAs($this->admin)->getJson('/invoices?status=PAID')
            ->assertStatus(200)->assertJsonPath('recordsFiltered', 1);

        $this->actingAs($this->admin)->getJson("/invoices?customer_id={$c1->id}")
            ->assertStatus(200)->assertJsonPath('recordsFiltered', 1);

        $this->actingAs($this->admin)->getJson('/invoices?period=2026-05')
            ->assertStatus(200)->assertJsonPath('recordsFiltered', 1);

        // 3. Announcement filter: status (is_active)
        Announcement::create(['title' => 'Pengumuman Aktif', 'body' => 'A', 'is_active' => true]);
        Announcement::create(['title' => 'Pengumuman Nonaktif', 'body' => 'B', 'is_active' => false]);

        $this->actingAs($this->admin)->getJson('/announcements?status=1')
            ->assertStatus(200)->assertJsonPath('recordsFiltered', 1);

        $this->actingAs($this->admin)->getJson('/announcements?status=0')
            ->assertStatus(200)->assertJsonPath('recordsFiltered', 1);
    }

    public function test_customer_json_response_for_offcanvas_does_not_expose_sensitive_attributes(): void
    {
        $customer = Customer::factory()->create([
            'password'             => Hash::make('secretPassword!'),
            'activation_code_hash' => Hash::make('ACT12345'),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonMissing(['password'])
            ->assertJsonMissing(['activation_code_hash'])
            ->assertJsonStructure([
                'id',
                'customer_code',
                'name',
                'phone',
                'address',
                'package',
                'billing_type',
                'service_status',
                'subscribed_at',
            ]);
    }

    public function test_store_customer_cannot_modify_activation_or_password_via_mass_assignment(): void
    {
        $pop = \App\Models\Pop::factory()->create(['code' => 'KTU', 'last_customer_number' => 0]);

        $payload = [
            'pop_id'                  => $pop->id,
            'name'                    => 'Budi Penyerang',
            'phone'                   => '6281234567899',
            'address'                 => 'Jl. Merdeka No. 10',
            'subscribed_at'           => now()->format('Y-m-d'),
            'package'                 => 'Paket 20 Mbps',
            'billing_type'            => 'Pascabayar',
            'service_status'          => 'ACTIVE',
            // Field jahat yang dilarang di-assign:
            'password'                => 'injected_password',
            'activation_code_hash'    => 'injected_hash',
            'activation_attempts'     => 99,
            'activation_locked_until' => '2099-01-01 00:00:00',
            'activated_at'            => '2026-01-01 00:00:00',
        ];

        $response = $this->actingAs($this->admin)->postJson('/customers', $payload);
        $response->assertStatus(201);

        $created = Customer::where('name', 'Budi Penyerang')->firstOrFail();
        $this->assertEquals('JCN-KTU-0001', $created->customer_code);
        $this->assertNull($created->password);
        $this->assertNull($created->activation_code_hash);
        $this->assertEquals(0, $created->activation_attempts);
        $this->assertNull($created->activation_locked_until);
        $this->assertNull($created->activated_at);
    }

    public function test_update_customer_cannot_modify_activation_or_password_via_mass_assignment(): void
    {
        $customer = Customer::factory()->create([
            'password'             => Hash::make('original_password'),
            'activation_code_hash' => 'original_hash',
            'activation_attempts'  => 3,
        ]);

        $payload = [
            'customer_code'        => $customer->customer_code,
            'name'                 => 'Nama Baru Terupdate',
            'phone'                => '6281234567899',
            'address'              => 'Jl. Baru No. 1',
            'subscribed_at'        => now()->format('Y-m-d'),
            'package'              => 'Paket 50 Mbps',
            'billing_type'         => 'Pascabayar',
            'service_status'       => 'ACTIVE',
            // Percobaan injeksi:
            'password'             => 'tampered_password',
            'activation_code_hash' => 'tampered_hash',
            'activation_attempts'  => 0,
        ];

        $response = $this->actingAs($this->admin)->putJson("/customers/{$customer->id}", $payload);
        $response->assertStatus(200);

        $customer->refresh();
        $this->assertEquals('Nama Baru Terupdate', $customer->name);
        $this->assertTrue(Hash::check('original_password', $customer->password));
        $this->assertEquals('original_hash', $customer->activation_code_hash);
        $this->assertEquals(3, $customer->activation_attempts);
    }
}
