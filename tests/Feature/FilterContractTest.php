<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterContractTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $surveyor;
    private User $technician;
    private User $noc;
    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pop = Pop::factory()->create(['name' => 'POP Uji Filter']);

        $this->superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN,
            'pop_id' => null,
        ]);

        $this->surveyor = User::factory()->create([
            'role' => Role::SURVEYOR,
            'pop_id' => $this->pop->id,
        ]);

        $this->technician = User::factory()->create([
            'role' => Role::TECHNICIAN,
            'pop_id' => $this->pop->id,
        ]);

        $this->noc = User::factory()->create([
            'role' => Role::NOC,
            'pop_id' => null,
        ]);
    }

    /**
     * 7a. Query string kotor/berlebih/invalid pada rute berfilter tidak boleh menghasilkan 500.
     */
    public function test_filtered_routes_gracefully_handle_dirty_or_invalid_query_parameters(): void
    {
        $dirtyQueries = [
            'q' => '<script>alert("xss")</script>',
            'search' => "' OR '1'='1",
            'pop_id' => 'abc',
            'status' => 'NON_EXISTENT_STATUS',
            'service_status' => 'UNKNOWN_ENUM_VALUE',
            'from' => 'invalid-date-format',
            'to' => '9999-99-99',
            'start_date' => '2026-12-31',
            'end_date' => '2026-01-01', // tanggal terbalik
            'random_param_inject' => 'DROP TABLE customers;',
            'period' => 'invalid_period_choice',
        ];

        // 1. Customers index
        $resCustomers = $this->actingAs($this->superAdmin)->get(route('customers.index', $dirtyQueries));
        $resCustomers->assertOk();

        // 2. Customers index AJAX (DataTables)
        $resCustomersAjax = $this->actingAs($this->superAdmin)->getJson(route('customers.index', array_merge($dirtyQueries, [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])));
        $resCustomersAjax->assertOk();

        // 3. Invoices index
        $resInvoices = $this->actingAs($this->superAdmin)->get(route('invoices.index', $dirtyQueries));
        $resInvoices->assertOk();

        // 4. Invoices index AJAX
        $resInvoicesAjax = $this->actingAs($this->superAdmin)->getJson(route('invoices.index', array_merge($dirtyQueries, [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])));
        $resInvoicesAjax->assertOk();

        // 5. Tickets index
        $resTickets = $this->actingAs($this->superAdmin)->get(route('tickets.index', $dirtyQueries));
        $resTickets->assertOk();

        // 6. Tickets index AJAX
        $resTicketsAjax = $this->actingAs($this->superAdmin)->getJson(route('tickets.index', array_merge($dirtyQueries, [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])));
        $resTicketsAjax->assertOk();

        // 7. Surveys index
        $resSurveys = $this->actingAs($this->superAdmin)->get(route('surveys.index', $dirtyQueries));
        $resSurveys->assertOk();

        // 8. Surveyor surveys index
        $resSurveyorSurveys = $this->actingAs($this->surveyor)->get(route('surveyor.surveys.index', $dirtyQueries));
        $resSurveyorSurveys->assertOk();

        // 9. Surveyor queue index
        $resSurveyorQueue = $this->actingAs($this->surveyor)->get(route('surveyor.queue.index', $dirtyQueries));
        $resSurveyorQueue->assertOk();

        // 10. Technician tasks index
        $resTechTasks = $this->actingAs($this->technician)->get(route('technician.tasks.index', $dirtyQueries));
        $resTechTasks->assertOk();

        // 11. NOC customer lookup index & search
        $resNocIndex = $this->actingAs($this->noc)->get(route('noc.customers.index', $dirtyQueries));
        $resNocIndex->assertOk();

        $resNocSearch = $this->actingAs($this->noc)->getJson(route('noc.customers.search', array_merge($dirtyQueries, [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])));
        $resNocSearch->assertOk();

        // 12. Dashboard
        $resDashboard = $this->actingAs($this->superAdmin)->get(route('dashboard', $dirtyQueries));
        $resDashboard->assertOk();
    }

    /**
     * 7b. Uji keseragaman parameter ekspor dengan parameter halaman daftar.
     */
    public function test_export_endpoints_accept_standard_filter_parameters(): void
    {
        $validParams = [
            'q' => 'Test',
            'status' => 'ACTIVE',
            'pop_id' => $this->pop->id,
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ];

        // Export Customers
        $resExpCust = $this->actingAs($this->superAdmin)->get(route('export.customers', $validParams));
        $resExpCust->assertOk();

        // Export Invoices
        $resExpInv = $this->actingAs($this->superAdmin)->get(route('export.invoices', $validParams));
        $resExpInv->assertOk();

        // Export Tickets
        $resExpTick = $this->actingAs($this->superAdmin)->get(route('export.tickets', array_merge($validParams, [
            'status' => 'OPEN',
        ])));
        $resExpTick->assertOk();

        // Export Installation Report
        $resExpInstall = $this->actingAs($this->superAdmin)->get(route('export.installation-report', $validParams));
        $resExpInstall->assertOk();

        // Parameter aliases juga harus diterima tanpa error
        $aliasParams = [
            'search' => 'Test',
            'service_status' => 'ACTIVE',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ];
        $this->actingAs($this->superAdmin)->get(route('export.customers', $aliasParams))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('export.invoices', $aliasParams))->assertOk();
    }
}

