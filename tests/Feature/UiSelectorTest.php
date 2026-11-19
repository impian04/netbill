<?php

namespace Tests\Feature;

use App\Models\Pop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UiSelectorTest — Fase 17d
 *
 * Renders every page that calls window.initServerTable() as the correct role,
 * then asserts that every selector referenced in the JS config exists in the
 * rendered HTML as the correct element type:
 *   • filterForm  → must be a <form> element (not a div or any other tag)
 *   • table       → must be a <table> element
 *   • searchInput → element with matching id must exist
 *
 * Also asserts the page returns HTTP 200.
 */
class UiSelectorTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;
    private User $superAdmin;
    private User $admin;
    private User $noc;
    private User $marketing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pop = Pop::factory()->create(['name' => 'POP Test']);

        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'pop_id' => null, 'is_active' => true]);
        $this->admin      = User::factory()->create(['role' => 'admin',       'pop_id' => $this->pop->id, 'is_active' => true]);
        $this->noc        = User::factory()->create(['role' => 'noc',         'pop_id' => null, 'is_active' => true]);
        $this->marketing  = User::factory()->create(['role' => 'marketing',   'pop_id' => $this->pop->id, 'is_active' => true]);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Assert that for a given page:
     *  1. GET returns 200
     *  2. filterForm selector (if provided) points to a <form> tag
     *  3. table selector points to a <table> tag
     *  4. searchInput selector (if provided) has a matching element
     */
    private function assertSelectorsPresent(
        User   $user,
        string $routeName,
        string $tableId,
        ?string $filterFormId   = null,
        ?string $searchInputId  = null,
        array  $routeParams     = [],
        string $pageLabel       = ''
    ): void {
        $label = $pageLabel ?: $routeName;

        $response = $this->actingAs($user)->get(route($routeName, $routeParams));
        $response->assertStatus(200, "Page [{$label}] did not return 200.");

        $html = $response->getContent();

        // --- table element ---
        $this->assertMatchesRegularExpression(
            '/<table[^>]+id="' . preg_quote($tableId, '/') . '"/',
            $html,
            "[{$label}] <table id=\"{$tableId}\"> not found in HTML."
        );

        // --- filterForm: must be <form>, not a div or other tag ---
        if ($filterFormId !== null) {
            // Must exist as a <form id="...">
            $this->assertMatchesRegularExpression(
                '/<form[^>]+id="' . preg_quote($filterFormId, '/') . '"/',
                $html,
                "[{$label}] filterForm '#$filterFormId' is not a <form> element. " .
                "Wrap filter controls inside <form id=\"{$filterFormId}\"> inside <x-filter-card>."
            );

            // Must NOT be only a div with that id (extra safety check)
            $this->assertDoesNotMatchRegularExpression(
                '/<div[^>]+id="' . preg_quote($filterFormId, '/') . '"/',
                $html,
                "[{$label}] filterForm '#$filterFormId' resolves to a <div>, not a <form>."
            );
        }

        // --- searchInput element must exist ---
        if ($searchInputId !== null) {
            $this->assertMatchesRegularExpression(
                '/id="' . preg_quote($searchInputId, '/') . '"/',
                $html,
                "[{$label}] searchInput '#$searchInputId' element not found in HTML."
            );
        }
    }

    // -------------------------------------------------------------------------
    // super_admin pages
    // -------------------------------------------------------------------------

    public function test_super_admin_customers_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'customers.index',
            'customers-table',
            'filter-customers-form',
            'filter-search',
        );
    }

    public function test_super_admin_invoices_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'invoices.index',
            'global-invoices-table',
            'filter-invoices-form',
            'filter-inv-search',
        );
    }

    public function test_super_admin_tickets_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'tickets.index',
            'ticketsTable',
            'filterTicketsForm',
            pageLabel: 'tickets.index (super_admin)',
        );
    }

    public function test_super_admin_surveys_index(): void
    {
        // surveys uses extraData function, no filterForm
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'surveys.index',
            'surveysTable',
            filterFormId: null,
            pageLabel: 'surveys.index (super_admin)',
        );
    }

    public function test_super_admin_work_orders_index(): void
    {
        // work-orders uses extraData function, no filterForm
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'work-orders.index',
            'workOrdersTable',
            filterFormId: null,
            pageLabel: 'work-orders.index (super_admin)',
        );
    }

    public function test_super_admin_staff_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'staff.index',
            'staff-table',
            'filter-staff-form',
            'filter-staff-search',
        );
    }

    public function test_super_admin_pops_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'pops.index',
            'pops-table',
            'filter-pops-form',
            'filter-pop-search',
        );
    }

    public function test_super_admin_odcs_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'odcs.index',
            'table-odcs',
            'filter-odcs-form',
            'filter-odc-search',
        );
    }

    public function test_super_admin_odps_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'odps.index',
            'table-odps',
            'filter-odps-form',
            'filter-odp-search',
        );
    }

    public function test_super_admin_materials_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'materials.index',
            'table-materials',
            'filter-materials-form',
            'filter-material-search',
        );
    }

    public function test_super_admin_announcements_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'announcements.index',
            'announcements-table',
            'filter-announcements-form',
            'filter-ann-search',
        );
    }

    public function test_super_admin_activity_logs_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'activity-logs.index',
            'activity-logs-table',
            'filter-logs-form',
            'filter-log-search',
        );
    }

    public function test_super_admin_marketing_fees_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'marketing-fees.index',
            'table-marketing-fees',
            'filter-fees-form',
            'filter-fee-search',
        );
    }

    public function test_super_admin_marketing_profiles_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'marketing-profiles.index',
            'table-marketing-profiles',
            'filter-profiles-form',
            'filter-profile-search',
        );
    }

    public function test_super_admin_customer_applications_index(): void
    {
        $this->assertSelectorsPresent(
            $this->superAdmin,
            'customer-applications.index',
            'table-admin-applications',
            'filter-customer-applications-form',
            'filter-admin-app-search',
        );
    }

    // -------------------------------------------------------------------------
    // NOC pages
    // -------------------------------------------------------------------------

    public function test_noc_customers_lookup_index(): void
    {
        $this->assertSelectorsPresent(
            $this->noc,
            'noc.customers.index',
            'nocCustomersTable',
            'filterNocCustomersForm',
            pageLabel: 'noc.customers.index',
        );
    }

    public function test_noc_tickets_index(): void
    {
        $this->assertSelectorsPresent(
            $this->noc,
            'tickets.index',
            'ticketsTable',
            'filterTicketsForm',
            pageLabel: 'tickets.index (noc)',
        );
    }

    // -------------------------------------------------------------------------
    // Admin pages
    // -------------------------------------------------------------------------

    public function test_admin_customer_applications_index(): void
    {
        $this->assertSelectorsPresent(
            $this->admin,
            'customer-applications.index',
            'table-admin-applications',
            'filter-customer-applications-form',
            'filter-admin-app-search',
            pageLabel: 'customer-applications.index (admin)',
        );
    }

    public function test_admin_surveys_index(): void
    {
        $this->assertSelectorsPresent(
            $this->admin,
            'surveys.index',
            'surveysTable',
            filterFormId: null,
            pageLabel: 'surveys.index (admin)',
        );
    }

    public function test_admin_work_orders_index(): void
    {
        $this->assertSelectorsPresent(
            $this->admin,
            'work-orders.index',
            'workOrdersTable',
            filterFormId: null,
            pageLabel: 'work-orders.index (admin)',
        );
    }

    // -------------------------------------------------------------------------
    // Marketing pages
    // -------------------------------------------------------------------------

    public function test_marketing_applications_index(): void
    {
        $this->assertSelectorsPresent(
            $this->marketing,
            'marketing.applications.index',
            'table-marketing-applications',
            'filter-applications-form',
            'filter-app-search',
            pageLabel: 'marketing.applications.index',
        );
    }
}

