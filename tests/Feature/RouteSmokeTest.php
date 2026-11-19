<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Announcement;
use App\Models\Customer;
use App\Models\CustomerApplication;
use App\Models\ImportBatch;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\MarketingFee;
use App\Models\MarketingProfile;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Pop;
use App\Models\Survey;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;
    private array $users = [];
    private array $models = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup POP dan Model Referensi Nyata
        $this->pop = Pop::factory()->create([
            'code' => 'KTU',
            'name' => 'POP Koto Tuo',
            'last_customer_number' => 0,
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        $admin      = User::factory()->admin($this->pop)->create();
        $marketing  = User::factory()->marketing($this->pop)->create();
        $surveyor   = User::factory()->surveyor($this->pop)->create();
        $technician = User::factory()->technician($this->pop)->create();
        $noc        = User::factory()->noc()->create();

        $this->users = [
            'super_admin' => $superAdmin,
            'admin'       => $admin,
            'marketing'   => $marketing,
            'surveyor'    => $surveyor,
            'technician'  => $technician,
            'noc'         => $noc,
        ];

        // Buat infrastruktur ODC & ODP & Material
        $odc = Odc::create([
            'pop_id' => $this->pop->id,
            'code'   => 'ODC-01',
            'name'   => 'ODC Pusat Koto Tuo',
            'total_ports' => 48,
        ]);

        $odp = Odp::create([
            'pop_id' => $this->pop->id,
            'odc_id' => $odc->id,
            'code'   => 'ODP-01',
            'name'   => 'ODP Tuo 01',
            'total_ports' => 8,
        ]);

        $material = Material::create([
            'pop_id'   => $this->pop->id,
            'code'     => 'DC-1C',
            'name'     => 'Drop Core 1 Core',
            'unit'     => 'Meter',
            'category' => 'Kabel',
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'odc_id' => $odc->id,
            'odp_id' => $odp->id,
        ]);

        $application = CustomerApplication::factory()->create([
            'pop_id'            => $this->pop->id,
            'marketing_user_id' => $marketing->id,
        ]);

        $survey = Survey::create([
            'customer_id'      => $customer->id,
            'pop_id'           => $this->pop->id,
            'surveyor_user_id' => $surveyor->id,
            'result'           => SurveyResult::FEASIBLE,
            'status'           => SurveyStatus::SUBMITTED,
        ]);

        $workOrder = WorkOrder::create([
            'customer_id'        => $customer->id,
            'survey_id'          => $survey->id,
            'pop_id'             => $this->pop->id,
            'technician_user_id' => $technician->id,
            'status'             => WorkOrderStatus::SUBMITTED,
            'completed_on'       => now()->format('Y-m-d'),
        ]);

        $ticket = Ticket::factory()->create([
            'pop_id'      => $this->pop->id,
            'customer_id' => $customer->id,
            'created_by'  => $noc->id,
        ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $announcement = Announcement::create([
            'title'     => 'Pemeliharaan Jaringan Demo',
            'body'      => 'Akan dilakukan pemeliharaan rutin malam ini.',
            'is_active' => true,
        ]);

        $importBatch = ImportBatch::create([
            'user_id'          => $admin->id,
            'pop_id'           => $this->pop->id,
            'file_path'        => 'imports/demo.xlsx',
            'original_name'    => 'demo.xlsx',
            'duplicate_policy' => 'skip',
            'status'           => 'done',
            'total_rows'       => 10,
            'processed_rows'   => 10,
        ]);

        $this->models = [
            'announcement' => $announcement->id,
            'application'  => $application->id,
            'customer'     => $customer->id,
            'invoice'      => $invoice->id,
            'importBatch'  => $importBatch->id,
            'user'         => $marketing->id,
            'material'     => $material->id,
            'survey'       => $survey->id,
            'workOrder'    => $workOrder->id,
            'odc'          => $odc->id,
            'odp'          => $odp->id,
            'pop'          => $this->pop->id,
            'staff'        => $admin->id,
            'ticket'       => $ticket->id,
            'type'         => 'materials',
            'token'        => 'dummy-token',
        ];
    }

    /**
     * Dapatkan semua route GET yang memenuhi syarat untuk diuji.
     */
    private function getEligibleGetRoutes(): array
    {
        $allRoutes = Route::getRoutes()->get('GET');
        $eligible = [];

        $excludedPrefixes = [
            'api.',
            'files.',
            'export.',
            'password.',
            'sanctum.',
        ];

        $excludedNames = [
            'login',
            'logout',
            'storage.local',
            'imports.preview',
            'master-import.preview',
            'master-import.template',
        ];

        foreach ($allRoutes as $route) {
            $name = $route->getName();
            if (! $name) {
                continue;
            }

            foreach ($excludedPrefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    continue 2;
                }
            }

            if (in_array($name, $excludedNames, true)) {
                continue;
            }

            $eligible[$name] = $route;
        }

        return $eligible;
    }

    /**
     * Selesaikan URL dengan mengisi parameter rute jika ada.
     */
    private function resolveUrl(\Illuminate\Routing\Route $route): string
    {
        $paramNames = $route->parameterNames();
        $params = [];

        foreach ($paramNames as $pName) {
            if (isset($this->models[$pName])) {
                $params[$pName] = $this->models[$pName];
            } else {
                $params[$pName] = 1;
            }
        }

        return route($route->getName(), $params);
    }

    /**
     * Iterasi semua GET route bernama untuk setiap role.
     * Pastikan status HTTP hanya 200, 302, 403, atau 404 (tidak boleh 500/exception).
     */
    public function test_all_named_get_routes_render_or_redirect_without_server_error_for_all_roles(): void
    {
        $routes = $this->getEligibleGetRoutes();
        $this->assertNotEmpty($routes, 'Tidak ada route GET yang ditemukan untuk diuji.');

        $allowedStatuses = [200, 302, 403, 404];

        foreach ($this->users as $roleName => $user) {
            foreach ($routes as $routeName => $route) {
                $url = $this->resolveUrl($route);

                // 1. Permintaan GET Standar
                $response = $this->actingAs($user)->get($url);
                $status = $response->getStatusCode();

                $this->assertTrue(
                    in_array($status, $allowedStatuses, true),
                    "FAIL: Route [{$routeName}] URL [{$url}] untuk role [{$roleName}] mengembalikan status [{$status}]. Status harus salah satu dari [200, 302, 403, 404]."
                );

                // 2. Jika route index atau search (DataTables), uji juga dengan parameter AJAX DataTables
                if (str_ends_with($routeName, '.index') || str_contains($routeName, 'search')) {
                    $dtResponse = $this->actingAs($user)->get(
                        $url . (str_contains($url, '?') ? '&' : '?') . 'draw=1&start=0&length=10',
                        ['X-Requested-With' => 'XMLHttpRequest']
                    );
                    $dtStatus = $dtResponse->getStatusCode();

                    $this->assertTrue(
                        in_array($dtStatus, $allowedStatuses, true),
                        "FAIL: DataTables AJAX route [{$routeName}] URL [{$url}] untuk role [{$roleName}] mengembalikan status [{$dtStatus}]."
                    );
                }
            }
        }
    }
}
