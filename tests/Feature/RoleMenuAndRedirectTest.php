<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pop;
use App\Models\User;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMenuAndRedirectTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;
    private User $superAdmin;
    private User $admin;
    private User $marketing;
    private User $surveyor;
    private User $technician;
    private User $noc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pop = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Koto Tuo']);

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->admin      = User::factory()->admin($this->pop)->create();
        $this->marketing  = User::factory()->marketing($this->pop)->create();
        $this->surveyor   = User::factory()->surveyor($this->pop)->create();
        $this->technician = User::factory()->technician($this->pop)->create();
        $this->noc        = User::factory()->noc()->create();
    }

    public function test_login_redirects_to_correct_landing_page_per_role(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $users = [
            [$this->superAdmin, route('dashboard')],
            [$this->admin,      route('dashboard')],
            [$this->marketing,  route('marketing.applications.index')],
            [$this->surveyor,   route('surveyor.queue.index')],
            [$this->technician, route('technician.tasks.index')],
            [$this->noc,        route('tickets.index')],
        ];

        foreach ($users as [$user, $expectedUrl]) {
            $response = $this->post(route('login.post'), [
                'email'    => $user->email,
                'password' => 'password',
            ]);

            $response->assertRedirect($expectedUrl);
            auth()->logout();
        }
    }

    public function test_portal_route_redirects_roles_with_landing_pages(): void
    {
        // 1. Marketing -> marketing.applications.index
        $this->actingAs($this->marketing)->get(route('portal'))
            ->assertRedirect(route('marketing.applications.index'));

        // 2. Surveyor -> surveyor.queue.index
        $this->actingAs($this->surveyor)->get(route('portal'))
            ->assertRedirect(route('surveyor.queue.index'));

        // 3. Technician -> technician.tasks.index
        $this->actingAs($this->technician)->get(route('portal'))
            ->assertRedirect(route('technician.tasks.index'));

        // 4. Admin -> dashboard
        $this->actingAs($this->admin)->get(route('portal'))
            ->assertRedirect(route('dashboard'));

        // 5. Super Admin -> dashboard
        $this->actingAs($this->superAdmin)->get(route('portal'))
            ->assertRedirect(route('dashboard'));

        // 6. NOC -> tickets.index
        $this->actingAs($this->noc)->get(route('portal'))
            ->assertRedirect(route('tickets.index'));
    }

    public function test_all_menu_links_for_each_role_return_200_ok(): void
    {
        $roleUsers = [
            [Role::SUPER_ADMIN, $this->superAdmin],
            [Role::ADMIN,       $this->admin],
            [Role::MARKETING,   $this->marketing],
            [Role::SURVEYOR,    $this->surveyor],
            [Role::TECHNICIAN,  $this->technician],
            [Role::NOC,         $this->noc],
        ];

        foreach ($roleUsers as [$role, $user]) {
            $routes = MenuService::getAllRoutesForRole($role);
            $this->assertNotEmpty($routes, "Role {$role->value} harus memiliki menu terdaftar.");

            foreach ($routes as $routeName) {
                $response = $this->actingAs($user)->get(route($routeName));
                $this->assertEquals(
                    200,
                    $response->status(),
                    "Route [{$routeName}] untuk role [{$role->value}] harus mengembalikan status 200, tetapi menerima {$response->status()}."
                );
            }
        }
    }

    public function test_roles_see_only_their_configured_menu_items_in_sidebar(): void
    {
        // 1. Marketing sees only "Pendaftaran Saya"
        $resMarketing = $this->actingAs($this->marketing)->get(route('marketing.applications.index'));
        $resMarketing->assertSee('Pendaftaran Saya');
        $resMarketing->assertDontSee('Pelanggan</a>', false);
        $resMarketing->assertDontSee('Survei</a>', false);
        $resMarketing->assertDontSee('Tugas Saya</a>', false);
        $resMarketing->assertDontSee('Manajemen Staf</a>', false);

        // 2. Surveyor sees "Antrean Survei" and "Survei Saya"
        $resSurveyor = $this->actingAs($this->surveyor)->get(route('surveyor.queue.index'));
        $resSurveyor->assertSee('Antrean Survei');
        $resSurveyor->assertSee('Survei Saya');
        $resSurveyor->assertDontSee('Tugas Saya</a>', false);
        $resSurveyor->assertDontSee('Pelanggan</a>', false);
        $resSurveyor->assertDontSee('Tagihan</a>', false);
        $resSurveyor->assertDontSee('Manajemen Staf</a>', false);

        // 3. Technician sees "Tugas Saya"
        $resTech = $this->actingAs($this->technician)->get(route('technician.tasks.index'));
        $resTech->assertSee('Tugas Saya');
        $resTech->assertDontSee('Antrean Survei</a>', false);
        $resTech->assertDontSee('Pelanggan</a>', false);
        $resTech->assertDontSee('Tagihan</a>', false);
        $resTech->assertDontSee('Manajemen Staf</a>', false);

        // 4. Admin sees operational & master menus, but NOT super_admin admin menus
        $resAdmin = $this->actingAs($this->admin)->get(route('dashboard'));
        $resAdmin->assertSee('Pelanggan');
        $resAdmin->assertSee('Survei');
        $resAdmin->assertSee('Penugasan');
        $resAdmin->assertSee('Master ODC');
        $resAdmin->assertSee('Master ODP');
        $resAdmin->assertSee('Master Barang');
        $resAdmin->assertDontSee('Manajemen Staf</a>', false);
        $resAdmin->assertDontSee('Manajemen POP</a>', false);
        $resAdmin->assertDontSee('Catatan Aktivitas</a>', false);

        // 5. Super Admin sees operational + administration menus
        $resSuperAdmin = $this->actingAs($this->superAdmin)->get(route('dashboard'));
        $resSuperAdmin->assertSee('Pelanggan');
        $resSuperAdmin->assertSee('Survei');
        $resSuperAdmin->assertSee('Penugasan');
        $resSuperAdmin->assertSee('Manajemen Staf');
        $resSuperAdmin->assertSee('Manajemen POP');
        $resSuperAdmin->assertSee('Catatan Aktivitas');

        // 6. NOC sees "Tiket" and "Cari Pelanggan"
        $resNoc = $this->actingAs($this->noc)->get(route('tickets.index'));
        $resNoc->assertSee('Tiket');
        $resNoc->assertSee('Cari Pelanggan');
        $resNoc->assertDontSee('Tagihan</a>', false);
        $resNoc->assertDontSee('Manajemen Staf</a>', false);
    }
}
