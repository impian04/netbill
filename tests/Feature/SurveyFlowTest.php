<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use App\Models\Customer;
use App\Models\MarketingFee;
use App\Models\Material;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Pop;
use App\Models\Survey;
use App\Models\SurveyItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyFlowTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;
    private Pop $popB;
    private User $superAdmin;
    private User $adminA;
    private User $adminB;
    private User $surveyorA1;
    private User $surveyorA2;
    private User $surveyorB;
    private User $marketing;
    private User $technician;

    private Odc $odcA;
    private Odp $odpA;
    private Material $matDropcoreA;
    private Material $matOntA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->popA = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Koto Tuo', 'last_customer_number' => 0]);
        $this->popB = Pop::factory()->create(['code' => 'EP', 'name' => 'POP Empat Petaling', 'last_customer_number' => 0]);

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->adminA = User::factory()->admin($this->popA)->create();
        $this->adminB = User::factory()->admin($this->popB)->create();
        $this->surveyorA1 = User::factory()->surveyor($this->popA)->create();
        $this->surveyorA2 = User::factory()->surveyor($this->popA)->create();
        $this->surveyorB = User::factory()->surveyor($this->popB)->create();
        $this->marketing = User::factory()->marketing($this->popA)->create();
        $this->technician = User::factory()->technician($this->popA)->create();

        $this->odcA = Odc::create(['pop_id' => $this->popA->id, 'name' => 'ODC-A-01', 'is_active' => true]);
        $this->odpA = Odp::create(['pop_id' => $this->popA->id, 'odc_id' => $this->odcA->id, 'name' => 'ODP-A-01-01', 'is_active' => true]);
        $this->matDropcoreA = Material::create(['pop_id' => $this->popA->id, 'name' => 'Dropcore KTU', 'unit' => 'meter', 'is_active' => true]);
        $this->matOntA = Material::create(['pop_id' => $this->popA->id, 'name' => 'ONT KTU', 'unit' => 'unit', 'is_active' => true]);
    }

    public function test_surveyor_sees_only_own_pop_queue_and_own_surveys(): void
    {
        // Customer Pending in POP A
        $custA = Customer::factory()->create([
            'pop_id'         => $this->popA->id,
            'service_status' => 'PENDING',
        ]);

        // Customer Pending in POP B
        $custB = Customer::factory()->create([
            'pop_id'         => $this->popB->id,
            'service_status' => 'PENDING',
        ]);

        // Surveyor A accesses queue
        $res = $this->actingAs($this->surveyorA1)->get(route('surveyor.queue.index'));
        $res->assertStatus(200);
        $res->assertSee($custA->customer_code);
        $res->assertDontSee($custB->customer_code);

        // Surveyor A cannot access survey created by Surveyor B
        $surveyB = Survey::create([
            'customer_id'      => $custB->id,
            'pop_id'           => $this->popB->id,
            'surveyor_user_id' => $this->surveyorB->id,
            'result'           => SurveyResult::FEASIBLE,
            'status'           => SurveyStatus::SUBMITTED,
        ]);

        $this->actingAs($this->surveyorA1)
            ->getJson(route('surveyor.surveys.show', $surveyB))
            ->assertStatus(404);
    }

    public function test_surveyor_json_and_views_do_not_expose_ktp_invoices_or_marketing(): void
    {
        $cust = Customer::factory()->create([
            'pop_id'              => $this->popA->id,
            'service_status'      => 'PENDING',
            'ktp_path'            => 'ktp/private_file.jpg',
            'referred_by_user_id' => $this->marketing->id,
        ]);

        $survey = Survey::create([
            'customer_id'      => $cust->id,
            'pop_id'           => $this->popA->id,
            'surveyor_user_id' => $this->surveyorA1->id,
            'result'           => SurveyResult::FEASIBLE,
            'status'           => SurveyStatus::SUBMITTED,
        ]);

        $res = $this->actingAs($this->surveyorA1)->getJson(route('surveyor.surveys.show', $survey));
        $res->assertStatus(200);

        $jsonStr = $res->getContent();
        $this->assertStringNotContainsString('private_file.jpg', $jsonStr);
        $this->assertStringNotContainsString('ktp_path', $jsonStr);
        $this->assertStringNotContainsString('invoices', $jsonStr);
        $this->assertStringNotContainsString('marketing_fee', $jsonStr);
        $this->assertStringNotContainsString('referred_by_user_id', $jsonStr);
    }

    public function test_two_surveyors_taking_same_customer_prevents_duplicate(): void
    {
        $cust = Customer::factory()->create([
            'pop_id'         => $this->popA->id,
            'service_status' => 'PENDING',
        ]);

        $payload = [
            'customer_id' => $cust->id,
            'odc_id'      => $this->odcA->id,
            'odp_id'      => $this->odpA->id,
            'coordinates' => '0.5100000, 101.4400000',
            'result'      => SurveyResult::FEASIBLE->value,
            'items'       => [
                ['material_id' => $this->matDropcoreA->id, 'quantity' => 100],
            ],
        ];

        // First surveyor submits
        $res1 = $this->actingAs($this->surveyorA1)->postJson(route('surveyor.surveys.store'), $payload);
        $res1->assertStatus(201);

        // Second surveyor tries to submit for same customer -> 422 with clear message
        $res2 = $this->actingAs($this->surveyorA2)->postJson(route('surveyor.surveys.store'), $payload);
        $res2->assertStatus(422);
        $this->assertStringContainsString('sudah disurvei', $res2->json('message') ?? json_encode($res2->json()));

        // Exactly 1 survey in database
        $this->assertEquals(1, Survey::where('customer_id', $cust->id)->count());
    }

    public function test_survey_form_validation_and_item_rules(): void
    {
        $cust = Customer::factory()->create(['pop_id' => $this->popA->id, 'service_status' => 'PENDING']);

        // 1. Invalid coordinates
        $res = $this->actingAs($this->surveyorA1)->postJson(route('surveyor.surveys.store'), [
            'customer_id' => $cust->id,
            'coordinates' => 'invalid-coords',
            'result'      => SurveyResult::FEASIBLE->value,
            'items'       => [['material_id' => $this->matDropcoreA->id, 'quantity' => 50]],
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors(['coordinates']);

        // 2. NOT_FEASIBLE requires result_note
        $res = $this->actingAs($this->surveyorA1)->postJson(route('surveyor.surveys.store'), [
            'customer_id' => $cust->id,
            'result'      => SurveyResult::NOT_FEASIBLE->value,
            'result_note' => '',
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors(['result_note']);

        // 3. FEASIBLE requires at least 1 item
        $res = $this->actingAs($this->surveyorA1)->postJson(route('surveyor.surveys.store'), [
            'customer_id' => $cust->id,
            'result'      => SurveyResult::FEASIBLE->value,
            'items'       => [],
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors(['items']);

        // 4. Duplicate material_id
        $res = $this->actingAs($this->surveyorA1)->postJson(route('surveyor.surveys.store'), [
            'customer_id' => $cust->id,
            'result'      => SurveyResult::FEASIBLE->value,
            'items'       => [
                ['material_id' => $this->matDropcoreA->id, 'quantity' => 50],
                ['material_id' => $this->matDropcoreA->id, 'quantity' => 20],
            ],
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors(['items.0.material_id']);

        // 5. Material from other POP
        $matB = Material::create(['pop_id' => $this->popB->id, 'name' => 'Dropcore EP', 'unit' => 'meter', 'is_active' => true]);
        $res = $this->actingAs($this->surveyorA1)->postJson(route('surveyor.surveys.store'), [
            'customer_id' => $cust->id,
            'result'      => SurveyResult::FEASIBLE->value,
            'items'       => [
                ['material_id' => $matB->id, 'quantity' => 50],
            ],
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    public function test_survey_update_rules_and_status_checks(): void
    {
        $cust = Customer::factory()->create(['pop_id' => $this->popA->id, 'service_status' => 'PENDING']);

        $survey = Survey::create([
            'customer_id'      => $cust->id,
            'pop_id'           => $this->popA->id,
            'surveyor_user_id' => $this->surveyorA1->id,
            'result'           => SurveyResult::FEASIBLE,
            'status'           => SurveyStatus::SUBMITTED,
        ]);

        SurveyItem::create([
            'survey_id'   => $survey->id,
            'material_id' => $this->matDropcoreA->id,
            'quantity'    => 80,
        ]);

        // 1. Update allowed when SUBMITTED
        $resUpdate1 = $this->actingAs($this->surveyorA1)->putJson(route('surveyor.surveys.update', $survey), [
            'result' => SurveyResult::FEASIBLE->value,
            'items'  => [
                ['material_id' => $this->matDropcoreA->id, 'quantity' => 120],
            ],
        ]);
        $resUpdate1->assertStatus(200);
        $this->assertEquals(120, $survey->fresh()->items->first()->quantity);

        // 2. Update allowed when RESURVEY_REQUESTED
        $survey->update(['status' => SurveyStatus::RESURVEY_REQUESTED]);
        $resUpdate2 = $this->actingAs($this->surveyorA1)->putJson(route('surveyor.surveys.update', $survey), [
            'result' => SurveyResult::FEASIBLE->value,
            'items'  => [
                ['material_id' => $this->matDropcoreA->id, 'quantity' => 150],
            ],
        ]);
        $resUpdate2->assertStatus(200);
        $this->assertEquals(SurveyStatus::SUBMITTED, $survey->fresh()->status);

        // 3. Update forbidden when ASSIGNED or CLOSED
        $survey->update(['status' => SurveyStatus::CLOSED]);
        $resClosed = $this->actingAs($this->surveyorA1)->putJson(route('surveyor.surveys.update', $survey), [
            'result' => SurveyResult::FEASIBLE->value,
            'items'  => [
                ['material_id' => $this->matDropcoreA->id, 'quantity' => 150],
            ],
        ]);
        $this->assertTrue(in_array($resClosed->status(), [403, 422]));
    }

    public function test_feasible_survey_updates_customer_odc_odp_and_coordinates(): void
    {
        $cust = Customer::factory()->create([
            'pop_id'         => $this->popA->id,
            'service_status' => 'PENDING',
            'odc_id'         => null,
            'odp_id'         => null,
            'latitude'       => null,
            'longitude'      => null,
        ]);

        $this->actingAs($this->surveyorA1)->postJson(route('surveyor.surveys.store'), [
            'customer_id' => $cust->id,
            'odc_id'      => $this->odcA->id,
            'odp_id'      => $this->odpA->id,
            'coordinates' => '0.5123456, 101.4456789',
            'result'      => SurveyResult::FEASIBLE->value,
            'items'       => [
                ['material_id' => $this->matDropcoreA->id, 'quantity' => 50],
            ],
        ])->assertStatus(201);

        $cust->refresh();
        $this->assertEquals($this->odcA->id, $cust->odc_id);
        $this->assertEquals($this->odpA->id, $cust->odp_id);
        $this->assertEquals(0.5123456, (float) $cust->latitude);
        $this->assertEquals(101.4456789, (float) $cust->longitude);
    }

    public function test_admin_request_resurvey_and_cancel_customer(): void
    {
        $cust = Customer::factory()->create([
            'pop_id'         => $this->popA->id,
            'service_status' => 'PENDING',
        ]);

        $survey = Survey::create([
            'customer_id'      => $cust->id,
            'pop_id'           => $this->popA->id,
            'surveyor_user_id' => $this->surveyorA1->id,
            'result'           => SurveyResult::FEASIBLE,
            'status'           => SurveyStatus::SUBMITTED,
        ]);

        // 1. Request Resurvey
        $resResurvey = $this->actingAs($this->adminA)->postJson(route('surveys.request-resurvey', $survey), [
            'review_note' => 'Tiang ODP terhalang pohon, cari alternatif.',
        ]);
        $resResurvey->assertStatus(200);

        $survey->refresh();
        $this->assertEquals(SurveyStatus::RESURVEY_REQUESTED, $survey->status);
        $this->assertEquals($this->adminA->id, $survey->reviewed_by);

        // 2. Cancel Customer
        $resCancel = $this->actingAs($this->adminA)->postJson(route('surveys.cancel-customer', $survey), [
            'cancel_reason' => 'Pelanggan membatalkan permintaan instalasi.',
        ]);
        $resCancel->assertStatus(200);

        $cust->refresh();
        $survey->refresh();
        $this->assertEquals('INACTIVE', $cust->service_status);
        $this->assertEquals(SurveyStatus::CLOSED, $survey->status);

        // Pastikan tidak ada fee marketing
        $this->assertEquals(0, MarketingFee::where('customer_id', $cust->id)->count());
    }

    public function test_role_access_matrix_and_pop_isolation(): void
    {
        // 1. Surveyor route access
        $this->actingAs($this->surveyorA1)->get(route('surveyor.queue.index'))->assertStatus(200);
        $this->actingAs($this->surveyorA1)->get(route('surveyor.surveys.index'))->assertStatus(200);

        // Non-surveyor gets 403 on surveyor routes
        $this->actingAs($this->marketing)->get(route('surveyor.queue.index'))->assertStatus(403);
        $this->actingAs($this->technician)->get(route('surveyor.queue.index'))->assertStatus(403);
        $this->actingAs($this->adminA)->get(route('surveyor.queue.index'))->assertStatus(403);

        // 2. Admin surveys route access
        $this->actingAs($this->adminA)->get(route('surveys.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('surveys.index'))->assertStatus(200);
        $this->actingAs($this->surveyorA1)->get(route('surveys.index'))->assertStatus(403);
        $this->actingAs($this->marketing)->get(route('surveys.index'))->assertStatus(403);

        // 3. Admin POP isolation
        $surveyB = Survey::create([
            'customer_id'      => Customer::factory()->create(['pop_id' => $this->popB->id])->id,
            'pop_id'           => $this->popB->id,
            'surveyor_user_id' => $this->surveyorB->id,
            'result'           => SurveyResult::FEASIBLE,
            'status'           => SurveyStatus::SUBMITTED,
        ]);

        $this->actingAs($this->adminA)
            ->getJson(route('surveys.show', $surveyB))
            ->assertStatus(404);

        $this->actingAs($this->adminA)
            ->postJson(route('surveys.request-resurvey', $surveyB), ['review_note' => 'Catatan'])
            ->assertStatus(404);
    }
}
