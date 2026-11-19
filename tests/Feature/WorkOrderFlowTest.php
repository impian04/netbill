<?php

namespace Tests\Feature;

use App\Enums\PhotoStage;
use App\Enums\Role;
use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\MarketingFee;
use App\Models\Material;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Pop;
use App\Models\Survey;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;
    private Pop $popB;
    private User $superAdmin;
    private User $adminA;
    private User $adminB;
    private User $marketing;
    private User $surveyor;
    private User $techA1;
    private User $techA2;
    private User $techB;

    private Odc $odcA;
    private Odp $odpA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->popA = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Koto Tuo', 'last_customer_number' => 0]);
        $this->popB = Pop::factory()->create(['code' => 'EP', 'name' => 'POP Empat Petaling', 'last_customer_number' => 0]);

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->adminA = User::factory()->admin($this->popA)->create();
        $this->adminB = User::factory()->admin($this->popB)->create();
        $this->marketing = User::factory()->marketing($this->popA)->create();
        $this->surveyor = User::factory()->surveyor($this->popA)->create();
        $this->techA1 = User::factory()->technician($this->popA)->create();
        $this->techA2 = User::factory()->technician($this->popA)->create();
        $this->techB = User::factory()->technician($this->popB)->create();

        $this->odcA = Odc::create(['pop_id' => $this->popA->id, 'name' => 'ODC-KTU-01', 'is_active' => true]);
        $this->odpA = Odp::create(['pop_id' => $this->popA->id, 'odc_id' => $this->odcA->id, 'name' => 'ODP-KTU-01', 'is_active' => true]);
    }

    public function test_full_flow_from_survey_assignment_to_verified_and_active(): void
    {
        // 1. Customer created via Marketing
        $cust = Customer::factory()->create([
            'pop_id'              => $this->popA->id,
            'service_status'      => 'PENDING',
            'registered_via'      => 'marketing',
            'referred_by_user_id' => $this->marketing->id,
            'odc_id'              => $this->odcA->id,
            'odp_id'              => $this->odpA->id,
            'latitude'            => 0.5101000,
            'longitude'           => 101.4402000,
        ]);

        // 2. Survey submitted & feasible
        $survey = Survey::create([
            'customer_id'      => $cust->id,
            'pop_id'           => $this->popA->id,
            'surveyor_user_id' => $this->surveyor->id,
            'odc_id'           => $this->odcA->id,
            'odp_id'           => $this->odpA->id,
            'latitude'         => 0.5101000,
            'longitude'        => 101.4402000,
            'result'           => SurveyResult::FEASIBLE,
            'status'           => SurveyStatus::SUBMITTED,
        ]);

        // 3. Admin assigns technician
        $resAssign = $this->actingAs($this->adminA)->postJson(route('surveys.assign-technician', $survey), [
            'technician_user_id' => $this->techA1->id,
            'scheduled_date'     => now()->addDay()->format('Y-m-d'),
            'admin_note'         => 'Pemasangan pagi jam 9.',
        ]);
        $resAssign->assertStatus(201);

        $this->assertDatabaseHas('work_orders', [
            'customer_id'        => $cust->id,
            'technician_user_id' => $this->techA1->id,
            'status'             => WorkOrderStatus::ASSIGNED->value,
        ]);
        $this->assertEquals(SurveyStatus::ASSIGNED, $survey->fresh()->status);

        $wo = WorkOrder::where('customer_id', $cust->id)->first();

        // 4. Technician starts work
        $resStart = $this->actingAs($this->techA1)->postJson(route('technician.tasks.start', $wo));
        $resStart->assertStatus(200);
        $this->assertEquals(WorkOrderStatus::IN_PROGRESS, $wo->fresh()->status);

        // 5. Technician uploads 2 photos
        $img1 = UploadedFile::fake()->image('instalasi.jpg', 800, 600);
        $resPhoto1 = $this->actingAs($this->techA1)->post(route('technician.tasks.photos.upload', $wo), [
            'photo'   => $img1,
            'stage'   => PhotoStage::INSTALASI->value,
            'caption' => 'Kabel dropcore ke tiang',
        ]);
        $resPhoto1->assertStatus(201);

        $img2 = UploadedFile::fake()->image('aktivasi.jpg', 800, 600);
        $resPhoto2 = $this->actingAs($this->techA1)->post(route('technician.tasks.photos.upload', $wo), [
            'photo'   => $img2,
            'stage'   => PhotoStage::AKTIVASI->value,
            'caption' => 'ONT aktif',
        ]);
        $resPhoto2->assertStatus(201);

        $this->assertEquals(2, $wo->photos()->count());

        // 6. Technician submits for verification
        $resSubmit = $this->actingAs($this->techA1)->postJson(route('technician.tasks.submit', $wo), [
            'completed_on'     => now()->format('Y-m-d'),
            'technician_notes' => 'Redaman -18 dBm, internet oke.',
        ]);
        $resSubmit->assertStatus(200);
        $this->assertEquals(WorkOrderStatus::SUBMITTED, $wo->fresh()->status);

        // 7. Admin verifies & activates customer
        $activatedDate = now()->format('Y-m-d');
        $resVerify = $this->actingAs($this->adminA)->postJson(route('work-orders.verify', $wo), [
            'activated_on' => $activatedDate,
        ]);
        $resVerify->assertStatus(200);

        $wo->refresh();
        $cust->refresh();

        $this->assertEquals(WorkOrderStatus::VERIFIED, $wo->status);
        $this->assertEquals('ACTIVE', $cust->service_status);
        $this->assertEquals($activatedDate, $cust->subscribed_at->format('Y-m-d'));

        // 8. Marketing fee generated exactly once
        $this->assertEquals(1, MarketingFee::where('customer_id', $cust->id)->count());
        $fee = MarketingFee::where('customer_id', $cust->id)->first();
        $this->assertEquals($this->marketing->id, $fee->marketing_user_id);
    }

    public function test_marketing_fee_not_created_for_admin_or_import_registration(): void
    {
        $cust = Customer::factory()->create([
            'pop_id'              => $this->popA->id,
            'service_status'      => 'PENDING',
            'registered_via'      => 'admin',
            'referred_by_user_id' => null,
        ]);

        $survey = Survey::create([
            'customer_id'      => $cust->id,
            'pop_id'           => $this->popA->id,
            'surveyor_user_id' => $this->surveyor->id,
            'result'           => SurveyResult::FEASIBLE,
            'status'           => SurveyStatus::ASSIGNED,
        ]);

        $wo = WorkOrder::create([
            'customer_id'        => $cust->id,
            'survey_id'          => $survey->id,
            'pop_id'             => $this->popA->id,
            'technician_user_id' => $this->techA1->id,
            'status'             => WorkOrderStatus::SUBMITTED,
            'completed_on'       => now()->format('Y-m-d'),
        ]);

        WorkOrderPhoto::create([
            'work_order_id' => $wo->id,
            'path'          => 'work_orders/test1.jpg',
            'stage'         => PhotoStage::INSTALASI,
            'uploaded_by'   => $this->techA1->id,
        ]);
        WorkOrderPhoto::create([
            'work_order_id' => $wo->id,
            'path'          => 'work_orders/test2.jpg',
            'stage'         => PhotoStage::AKTIVASI,
            'uploaded_by'   => $this->techA1->id,
        ]);

        $this->actingAs($this->adminA)->postJson(route('work-orders.verify', $wo))->assertStatus(200);

        $cust->refresh();
        $this->assertEquals('ACTIVE', $cust->service_status);
        $this->assertEquals(0, MarketingFee::where('customer_id', $cust->id)->count());
    }

    public function test_technician_sees_only_own_tasks_and_cross_pop_isolation(): void
    {
        $custA = Customer::factory()->create(['pop_id' => $this->popA->id]);
        $surveyA = Survey::create(['customer_id' => $custA->id, 'pop_id' => $this->popA->id, 'surveyor_user_id' => $this->surveyor->id, 'result' => SurveyResult::FEASIBLE, 'status' => SurveyStatus::ASSIGNED]);
        $woA = WorkOrder::create(['customer_id' => $custA->id, 'survey_id' => $surveyA->id, 'pop_id' => $this->popA->id, 'technician_user_id' => $this->techA1->id, 'status' => WorkOrderStatus::ASSIGNED]);

        $custB = Customer::factory()->create(['pop_id' => $this->popB->id]);
        $surveyB = Survey::create(['customer_id' => $custB->id, 'pop_id' => $this->popB->id, 'surveyor_user_id' => $this->surveyor->id, 'result' => SurveyResult::FEASIBLE, 'status' => SurveyStatus::ASSIGNED]);
        $woB = WorkOrder::create(['customer_id' => $custB->id, 'survey_id' => $surveyB->id, 'pop_id' => $this->popB->id, 'technician_user_id' => $this->techB->id, 'status' => WorkOrderStatus::ASSIGNED]);

        // Tech A1 sees WO A but gets 404 for WO B
        $this->actingAs($this->techA1)->getJson(route('technician.tasks.show', $woA))->assertStatus(200);
        $this->actingAs($this->techA1)->getJson(route('technician.tasks.show', $woB))->assertStatus(404);

        // Security: No sensitive fields in JSON
        $custA->update(['ktp_path' => 'ktp/secret.jpg']);
        $json = $this->actingAs($this->techA1)->getJson(route('technician.tasks.show', $woA))->getContent();
        $this->assertStringNotContainsString('secret.jpg', $json);
        $this->assertStringNotContainsString('invoices', $json);
        $this->assertStringNotContainsString('marketing_fees', $json);
    }

    public function test_assignment_requires_active_technician_in_same_pop(): void
    {
        $cust = Customer::factory()->create(['pop_id' => $this->popA->id]);
        $survey = Survey::create(['customer_id' => $cust->id, 'pop_id' => $this->popA->id, 'surveyor_user_id' => $this->surveyor->id, 'result' => SurveyResult::FEASIBLE, 'status' => SurveyStatus::SUBMITTED]);

        // 1. Assign to technician in POP B -> 422
        $resOtherPop = $this->actingAs($this->adminA)->postJson(route('surveys.assign-technician', $survey), [
            'technician_user_id' => $this->techB->id,
        ]);
        $resOtherPop->assertStatus(422);

        // 2. Assign to inactive technician -> 422
        $inactiveTech = User::factory()->technician($this->popA)->create(['is_active' => false]);
        $resInactive = $this->actingAs($this->adminA)->postJson(route('surveys.assign-technician', $survey), [
            'technician_user_id' => $inactiveTech->id,
        ]);
        $resInactive->assertStatus(422);
    }

    public function test_reassign_and_cancel_work_order(): void
    {
        $cust = Customer::factory()->create(['pop_id' => $this->popA->id]);
        $survey = Survey::create(['customer_id' => $cust->id, 'pop_id' => $this->popA->id, 'surveyor_user_id' => $this->surveyor->id, 'result' => SurveyResult::FEASIBLE, 'status' => SurveyStatus::ASSIGNED]);
        $wo = WorkOrder::create(['customer_id' => $cust->id, 'survey_id' => $survey->id, 'pop_id' => $this->popA->id, 'technician_user_id' => $this->techA1->id, 'status' => WorkOrderStatus::ASSIGNED]);

        // Reassign to Tech A2
        $resReassign = $this->actingAs($this->adminA)->postJson(route('work-orders.reassign', $wo), [
            'technician_user_id' => $this->techA2->id,
        ]);
        $resReassign->assertStatus(200);
        $this->assertEquals($this->techA2->id, $wo->fresh()->technician_user_id);

        // Cancel Work Order -> WO CANCELLED, Survey reverts to SUBMITTED
        $resCancel = $this->actingAs($this->adminA)->postJson(route('work-orders.cancel', $wo), [
            'cancel_reason' => 'Teknisi sedang sakit dan stok material habis.',
        ]);
        $resCancel->assertStatus(200);

        $this->assertEquals(WorkOrderStatus::CANCELLED, $wo->fresh()->status);
        $this->assertEquals(SurveyStatus::SUBMITTED, $survey->fresh()->status);
    }

    public function test_photo_upload_validations_and_limits(): void
    {
        $cust = Customer::factory()->create(['pop_id' => $this->popA->id]);
        $survey = Survey::create(['customer_id' => $cust->id, 'pop_id' => $this->popA->id, 'surveyor_user_id' => $this->surveyor->id, 'result' => SurveyResult::FEASIBLE, 'status' => SurveyStatus::ASSIGNED]);
        $wo = WorkOrder::create(['customer_id' => $cust->id, 'survey_id' => $survey->id, 'pop_id' => $this->popA->id, 'technician_user_id' => $this->techA1->id, 'status' => WorkOrderStatus::IN_PROGRESS]);

        // 1. Invalid file format
        $txtFile = UploadedFile::fake()->create('dokumen.txt', 100);
        $resBadMime = $this->actingAs($this->techA1)->postJson(route('technician.tasks.photos.upload', $wo), [
            'photo' => $txtFile,
            'stage' => PhotoStage::INSTALASI->value,
        ]);
        $resBadMime->assertStatus(422);

        // 2. Limit 12 photos
        for ($i = 0; $i < 12; $i++) {
            WorkOrderPhoto::create([
                'work_order_id' => $wo->id,
                'path'          => "work_orders/fake_{$i}.jpg",
                'stage'         => PhotoStage::INSTALASI,
                'uploaded_by'   => $this->techA1->id,
            ]);
        }

        $extraPhoto = UploadedFile::fake()->image('extra.jpg', 800, 600);
        $resMax = $this->actingAs($this->techA1)->postJson(route('technician.tasks.photos.upload', $wo), [
            'photo' => $extraPhoto,
            'stage' => PhotoStage::INSTALASI->value,
        ]);
        $resMax->assertStatus(422);
    }

    public function test_verification_requires_submitted_and_minimum_photos(): void
    {
        $cust = Customer::factory()->create(['pop_id' => $this->popA->id]);
        $survey = Survey::create(['customer_id' => $cust->id, 'pop_id' => $this->popA->id, 'surveyor_user_id' => $this->surveyor->id, 'result' => SurveyResult::FEASIBLE, 'status' => SurveyStatus::ASSIGNED]);
        $wo = WorkOrder::create(['customer_id' => $cust->id, 'survey_id' => $survey->id, 'pop_id' => $this->popA->id, 'technician_user_id' => $this->techA1->id, 'status' => WorkOrderStatus::IN_PROGRESS]);

        // Attempt verification on IN_PROGRESS -> 422
        $this->actingAs($this->adminA)->postJson(route('work-orders.verify', $wo))->assertStatus(422);

        // Status SUBMITTED but 0 photos -> 422
        $wo->update(['status' => WorkOrderStatus::SUBMITTED]);
        $this->actingAs($this->adminA)->postJson(route('work-orders.verify', $wo))->assertStatus(422);

        // 1 photo only -> 422
        WorkOrderPhoto::create([
            'work_order_id' => $wo->id,
            'path'          => 'work_orders/p1.jpg',
            'stage'         => PhotoStage::INSTALASI,
            'uploaded_by'   => $this->techA1->id,
        ]);
        $this->actingAs($this->adminA)->postJson(route('work-orders.verify', $wo))->assertStatus(422);

        // 2 photos -> 200
        WorkOrderPhoto::create([
            'work_order_id' => $wo->id,
            'path'          => 'work_orders/p2.jpg',
            'stage'         => PhotoStage::AKTIVASI,
            'uploaded_by'   => $this->techA1->id,
        ]);
        $this->actingAs($this->adminA)->postJson(route('work-orders.verify', $wo))->assertStatus(200);
    }

    public function test_editing_and_deleting_photos_allowed_only_in_valid_statuses(): void
    {
        $cust = Customer::factory()->create(['pop_id' => $this->popA->id]);
        $survey = Survey::create(['customer_id' => $cust->id, 'pop_id' => $this->popA->id, 'surveyor_user_id' => $this->surveyor->id, 'result' => SurveyResult::FEASIBLE, 'status' => SurveyStatus::ASSIGNED]);
        $wo = WorkOrder::create(['customer_id' => $cust->id, 'survey_id' => $survey->id, 'pop_id' => $this->popA->id, 'technician_user_id' => $this->techA1->id, 'status' => WorkOrderStatus::IN_PROGRESS]);

        $photo = WorkOrderPhoto::create([
            'work_order_id' => $wo->id,
            'path'          => 'work_orders/del_test.jpg',
            'stage'         => PhotoStage::INSTALASI,
            'uploaded_by'   => $this->techA1->id,
        ]);

        // Allowed on IN_PROGRESS
        $resDel = $this->actingAs($this->techA1)->deleteJson(route('technician.tasks.photos.delete', ['workOrder' => $wo, 'photo' => $photo]));
        $resDel->assertStatus(200);
        $this->assertDatabaseMissing('work_order_photos', ['id' => $photo->id]);

        // Disallowed on VERIFIED
        $photo2 = WorkOrderPhoto::create([
            'work_order_id' => $wo->id,
            'path'          => 'work_orders/del_test2.jpg',
            'stage'         => PhotoStage::INSTALASI,
            'uploaded_by'   => $this->techA1->id,
        ]);
        $wo->update(['status' => WorkOrderStatus::VERIFIED]);

        $resDelVerified = $this->actingAs($this->techA1)->deleteJson(route('technician.tasks.photos.delete', ['workOrder' => $wo, 'photo' => $photo2]));
        $resDelVerified->assertStatus(422);
    }

    public function test_role_access_matrix_for_work_orders(): void
    {
        $this->actingAs($this->adminA)->get(route('work-orders.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('work-orders.index'))->assertStatus(200);
        $this->actingAs($this->techA1)->get(route('technician.tasks.index'))->assertStatus(200);

        // Forbidden roles
        $this->actingAs($this->surveyor)->get(route('work-orders.index'))->assertStatus(403);
        $this->actingAs($this->surveyor)->get(route('technician.tasks.index'))->assertStatus(403);
        $this->actingAs($this->marketing)->get(route('work-orders.index'))->assertStatus(403);
        $this->actingAs($this->marketing)->get(route('technician.tasks.index'))->assertStatus(403);
    }
}
