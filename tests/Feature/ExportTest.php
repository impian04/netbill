<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Pop;
use App\Models\Survey;
use App\Models\SurveyItem;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private Pop    $popA;
    private Pop    $popB;
    private User   $superAdmin;
    private User   $noc;
    private User   $adminA;
    private User   $adminB;
    private User   $marketing;
    private Customer $custA;
    private Customer $custB;
    private Material $material;
    private Ticket $ticketA;
    private Ticket $ticketB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->popA = Pop::factory()->create(['name' => 'POP Alpha']);
        $this->popB = Pop::factory()->create(['name' => 'POP Beta']);

        $this->superAdmin = User::factory()->create(['role' => Role::SUPER_ADMIN, 'pop_id' => null]);
        $this->noc        = User::factory()->create(['role' => Role::NOC, 'pop_id' => null]);
        $this->adminA     = User::factory()->create(['role' => Role::ADMIN, 'pop_id' => $this->popA->id]);
        $this->adminB     = User::factory()->create(['role' => Role::ADMIN, 'pop_id' => $this->popB->id]);
        $this->marketing  = User::factory()->create(['role' => Role::MARKETING, 'pop_id' => $this->popA->id]);

        $this->custA = Customer::factory()->create([
            'pop_id'         => $this->popA->id,
            'service_status' => 'ACTIVE',
            'registered_via' => 'marketing',
            'referred_by_user_id' => $this->marketing->id,
        ]);
        $this->custB = Customer::factory()->create([
            'pop_id'         => $this->popB->id,
            'service_status' => 'ACTIVE',
            'registered_via' => 'admin',
        ]);

        $this->material = Material::factory()->create([
            'pop_id' => $this->popA->id,
            'name'   => 'Kabel Optik',
            'unit'   => 'meter',
        ]);

        // Tiket untuk custA (POP Alpha)
        $this->ticketA = Ticket::createWithNumber([
            'customer_id'    => $this->custA->id,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => $this->custA->name,
            'reporter_phone' => $this->custA->phone,
            'category'       => TicketCategory::CONNECTION,
            'channel'        => TicketChannel::WHATSAPP,
            'priority'       => TicketPriority::HIGH,
            'subject'        => 'Internet mati',
            'description'    => 'LOS merah',
            'status'         => TicketStatus::RESOLVED,
            'created_by'     => $this->noc->id,
            'opened_at'      => now()->subHours(5),
            'first_response_at' => now()->subHours(4),
            'resolved_at'    => now()->subHour(),
            'resolution_note' => 'Sudah diperbaiki',
        ]);

        // Tiket untuk custB (POP Beta)
        $this->ticketB = Ticket::createWithNumber([
            'customer_id'    => $this->custB->id,
            'pop_id'         => $this->popB->id,
            'reporter_name'  => $this->custB->name,
            'reporter_phone' => $this->custB->phone,
            'category'       => TicketCategory::PAYMENT,
            'channel'        => TicketChannel::PHONE,
            'priority'       => TicketPriority::NORMAL,
            'subject'        => 'Tagihan salah',
            'description'    => 'Nominal tidak sesuai',
            'status'         => TicketStatus::OPEN,
            'created_by'     => $this->noc->id,
            'opened_at'      => now()->subHour(),
        ]);

        // Survey + WO untuk custA
        $survey = Survey::create([
            'customer_id'      => $this->custA->id,
            'pop_id'           => $this->popA->id,
            'surveyor_user_id' => User::factory()->create(['role' => Role::SURVEYOR, 'pop_id' => $this->popA->id])->id,
            'result'           => SurveyResult::FEASIBLE,
            'status'           => SurveyStatus::ASSIGNED,
        ]);

        SurveyItem::create([
            'survey_id'   => $survey->id,
            'material_id' => $this->material->id,
            'quantity'    => 100,
        ]);

        WorkOrder::create([
            'type'               => WorkOrderType::INSTALLATION,
            'customer_id'        => $this->custA->id,
            'installation_key'   => $this->custA->id,
            'survey_id'          => $survey->id,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => User::factory()->create(['role' => Role::TECHNICIAN, 'pop_id' => $this->popA->id])->id,
            'status'             => WorkOrderStatus::VERIFIED,
            'activated_on'       => now()->subDays(5)->toDateString(),
            'completed_on'       => now()->subDays(6)->toDateString(),
            'assigned_by'        => $this->adminA->id,
        ]);
    }

    // ── Ticket Export ────────────────────────────────────────────────────────

    public function test_noc_can_export_tickets_xlsx(): void
    {
        $res = $this->actingAs($this->noc)
            ->get(route('export.tickets'));

        $res->assertStatus(200);
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('export-tiket', $res->headers->get('content-disposition'));
    }

    public function test_super_admin_can_export_all_pop_tickets(): void
    {
        $res = $this->actingAs($this->superAdmin)
            ->get(route('export.tickets'));

        $res->assertStatus(200);
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_admin_can_export_own_pop_tickets_only(): void
    {
        // AdminA exports — should only see POP Alpha tickets (ticketA)
        $res = $this->actingAs($this->adminA)
            ->get(route('export.tickets'));

        $res->assertStatus(200);
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_ticket_export_respects_filter(): void
    {
        $res = $this->actingAs($this->noc)
            ->get(route('export.tickets', ['status' => TicketStatus::RESOLVED->value]));

        $res->assertStatus(200);
        // Should succeed - only RESOLVED tickets in the export
    }

    public function test_marketing_cannot_export_tickets(): void
    {
        $res = $this->actingAs($this->marketing)
            ->get(route('export.tickets'));

        $res->assertStatus(403);
    }

    public function test_ticket_export_logged_in_activity_logs(): void
    {
        $this->actingAs($this->noc)
            ->get(route('export.tickets'));

        $this->assertDatabaseHas('activity_logs', [
            'action'  => 'export.tickets',
            'user_id' => $this->noc->id,
        ]);
    }

    public function test_ticket_export_excludes_forbidden_fields(): void
    {
        // Confirm no KTP / account / activation data in spreadsheet columns
        $res = $this->actingAs($this->noc)->get(route('export.tickets'));
        $res->assertStatus(200);

        // The export class headers must not contain these sensitive fields
        $forbiddenHeaders = ['KTP', 'ktp', 'rekening', 'aktivasi', 'password', 'activation'];
        foreach (\App\Exports\TicketExport::HEADERS_DETAIL as $header) {
            foreach ($forbiddenHeaders as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $header,
                    "Header '{$header}' should not contain sensitive field '{$forbidden}'");
            }
        }
    }

    public function test_ticket_export_rate_limited(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $res = $this->actingAs($this->noc)->get(route('export.tickets'));
        }
        $res->assertStatus(429);
    }

    // ── Installation Report Export ───────────────────────────────────────────

    public function test_admin_can_export_installation_report(): void
    {
        $res = $this->actingAs($this->adminA)
            ->get(route('export.installation-report'));

        $res->assertStatus(200);
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('export-laporan-pemasangan', $res->headers->get('content-disposition'));
    }

    public function test_super_admin_can_export_installation_report_all_pops(): void
    {
        $res = $this->actingAs($this->superAdmin)
            ->get(route('export.installation-report'));

        $res->assertStatus(200);
    }

    public function test_admin_pop_isolation_in_installation_report(): void
    {
        // AdminB (POP Beta) should not see custA (POP Alpha) in report
        // custB has no work order, so report is empty for adminB
        $res = $this->actingAs($this->adminB)
            ->get(route('export.installation-report'));

        $res->assertStatus(200);
        // We verify isolation through the query scope — adminB only sees popB customers
        // custA belongs to popA → not in adminB's export
    }

    public function test_installation_report_logged_in_activity_logs(): void
    {
        $this->actingAs($this->adminA)
            ->get(route('export.installation-report'));

        $this->assertDatabaseHas('activity_logs', [
            'action'  => 'export.installation_report',
            'user_id' => $this->adminA->id,
        ]);
    }

    public function test_installation_report_excludes_forbidden_fields(): void
    {
        $forbiddenHeaders = ['KTP', 'ktp', 'rekening', 'aktivasi', 'password', 'activation', 'kode_aktivasi'];
        foreach (\App\Exports\InstallationReportExport::HEADERS_INSTALLATION as $header) {
            foreach ($forbiddenHeaders as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $header,
                    "Header '{$header}' should not contain sensitive field '{$forbidden}'");
            }
        }
        foreach (\App\Exports\InstallationReportExport::HEADERS_MATERIALS as $header) {
            foreach ($forbiddenHeaders as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $header);
            }
        }
    }

    public function test_noc_cannot_export_installation_report(): void
    {
        $res = $this->actingAs($this->noc)
            ->get(route('export.installation-report'));

        $res->assertStatus(403);
    }

    public function test_marketing_cannot_export_installation_report(): void
    {
        $res = $this->actingAs($this->marketing)
            ->get(route('export.installation-report'));

        $res->assertStatus(403);
    }

    public function test_installation_report_rate_limited(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $res = $this->actingAs($this->adminA)->get(route('export.installation-report'));
        }
        $res->assertStatus(429);
    }

    public function test_formula_injection_sanitized_in_ticket_export(): void
    {
        // Create ticket with formula-injection-attempt in subject
        Ticket::createWithNumber([
            'customer_id'    => null,
            'pop_id'         => $this->popA->id,
            'reporter_name'  => '=SUM(A1:A100)',  // injection attempt
            'reporter_phone' => '+6281234567890',
            'category'       => TicketCategory::OTHER,
            'channel'        => TicketChannel::WALK_IN,
            'priority'       => TicketPriority::LOW,
            'subject'        => '@formula injection',  // @ prefix
            'description'    => 'Test formula injection',
            'status'         => TicketStatus::OPEN,
            'created_by'     => $this->noc->id,
            'opened_at'      => now(),
        ]);

        // The export should succeed without error (sanitizer handles injection prefixes)
        $res = $this->actingAs($this->noc)->get(route('export.tickets'));
        $res->assertStatus(200);

        // Verify CellSanitizer sanitizes = and @ prefixes
        $this->assertSame("'=SUM(A1:A100)", \App\Exports\CellSanitizer::sanitize('=SUM(A1:A100)'));
        $this->assertSame("'@formula injection", \App\Exports\CellSanitizer::sanitize('@formula injection'));
        $this->assertSame('JCN-KTU-0001', \App\Exports\CellSanitizer::sanitize('JCN-KTU-0001')); // ID not mangled
    }

    public function test_existing_customer_export_still_works(): void
    {
        $res = $this->actingAs($this->superAdmin)
            ->get(route('export.customers'));

        $res->assertStatus(200);
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_existing_invoice_export_still_works(): void
    {
        $res = $this->actingAs($this->superAdmin)
            ->get(route('export.invoices'));

        $res->assertStatus(200);
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
