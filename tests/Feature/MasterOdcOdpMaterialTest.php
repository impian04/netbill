<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MasterOdcOdpMaterialTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;
    private Pop $popB;
    private User $superAdmin;
    private User $adminA;
    private User $adminB;
    private User $marketing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->popA = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Koto Tuo', 'last_customer_number' => 0]);
        $this->popB = Pop::factory()->create(['code' => 'EP', 'name' => 'POP Empat Petaling', 'last_customer_number' => 0]);

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->adminA = User::factory()->admin($this->popA)->create();
        $this->adminB = User::factory()->admin($this->popB)->create();
        $this->marketing = User::factory()->marketing($this->popA)->create();
    }

    public function test_access_control_for_odc_odp_material_routes(): void
    {
        // Super Admin & Admin POP can access
        $this->actingAs($this->superAdmin)->get(route('odcs.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('odps.index'))->assertStatus(200);
        $this->actingAs($this->superAdmin)->get(route('materials.index'))->assertStatus(200);

        $this->actingAs($this->adminA)->get(route('odcs.index'))->assertStatus(200);
        $this->actingAs($this->adminA)->get(route('odps.index'))->assertStatus(200);
        $this->actingAs($this->adminA)->get(route('materials.index'))->assertStatus(200);

        // Marketing / non-admin is forbidden (403)
        $this->actingAs($this->marketing)->get(route('odcs.index'))->assertStatus(403);
        $this->actingAs($this->marketing)->get(route('odps.index'))->assertStatus(403);
        $this->actingAs($this->marketing)->get(route('materials.index'))->assertStatus(403);
    }

    public function test_pop_isolation_for_odc_odp_material(): void
    {
        $odcB = Odc::create([
            'pop_id' => $this->popB->id,
            'name' => 'ODC-B-01',
            'is_active' => true,
        ]);

        $odpB = Odp::create([
            'pop_id' => $this->popB->id,
            'odc_id' => $odcB->id,
            'name' => 'ODP-B-01-01',
            'is_active' => true,
        ]);

        $materialB = Material::create([
            'pop_id' => $this->popB->id,
            'name' => 'Dropcore B',
            'unit' => 'meter',
            'is_active' => true,
        ]);

        // Admin A tries to edit or delete POP B items -> 403 or 404 (POP isolation)
        $resOdcUpdate = $this->actingAs($this->adminA)
            ->putJson(route('odcs.update', $odcB), ['name' => 'ODC-Hacked']);
        $this->assertTrue(in_array($resOdcUpdate->status(), [403, 404]));

        $resOdcDelete = $this->actingAs($this->adminA)
            ->deleteJson(route('odcs.destroy', $odcB));
        $this->assertTrue(in_array($resOdcDelete->status(), [403, 404]));

        $resOdpUpdate = $this->actingAs($this->adminA)
            ->putJson(route('odps.update', $odpB), ['name' => 'ODP-Hacked', 'odc_id' => $odcB->id]);
        $this->assertTrue(in_array($resOdpUpdate->status(), [403, 404]));

        $resOdpDelete = $this->actingAs($this->adminA)
            ->deleteJson(route('odps.destroy', $odpB));
        $this->assertTrue(in_array($resOdpDelete->status(), [403, 404]));

        $resMatUpdate = $this->actingAs($this->adminA)
            ->putJson(route('materials.update', $materialB), ['name' => 'Material-Hacked', 'unit' => 'meter']);
        $this->assertTrue(in_array($resMatUpdate->status(), [403, 404]));

        $resMatDelete = $this->actingAs($this->adminA)
            ->deleteJson(route('materials.destroy', $materialB));
        $this->assertTrue(in_array($resMatDelete->status(), [403, 404]));
    }

    public function test_odp_must_belong_to_odc_in_same_pop(): void
    {
        $odcB = Odc::create([
            'pop_id' => $this->popB->id,
            'name' => 'ODC-B-01',
            'is_active' => true,
        ]);

        // Admin A attempts to create ODP in POP A referencing ODC in POP B
        $response = $this->actingAs($this->adminA)->postJson(route('odps.store'), [
            'name' => 'ODP-A-01',
            'odc_id' => $odcB->id,
            'pop_id' => $this->popA->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['odc_id']);

        // Correct ODC in POP A succeeds
        $odcA = Odc::create([
            'pop_id' => $this->popA->id,
            'name' => 'ODC-A-01',
            'is_active' => true,
        ]);

        $success = $this->actingAs($this->adminA)->postJson(route('odps.store'), [
            'name' => 'ODP-A-01',
            'odc_id' => $odcA->id,
            'pop_id' => $this->popA->id,
        ]);

        $this->assertTrue(in_array($success->status(), [200, 201]));
        $this->assertDatabaseHas('odps', [
            'name' => 'ODP-A-01',
            'pop_id' => $this->popA->id,
            'odc_id' => $odcA->id,
        ]);
    }

    public function test_deletion_protection_when_used_and_toggle_active(): void
    {
        $odc = Odc::create([
            'pop_id' => $this->popA->id,
            'name' => 'ODC-USED',
            'is_active' => true,
        ]);

        $odp = Odp::create([
            'pop_id' => $this->popA->id,
            'odc_id' => $odc->id,
            'name' => 'ODP-USED',
            'is_active' => true,
        ]);

        // ODC cannot be deleted because ODP uses it
        $resDelOdc = $this->actingAs($this->adminA)
            ->deleteJson(route('odcs.destroy', $odc));
        $resDelOdc->assertStatus(422);
        $this->assertStringContainsString('menonaktifkan', (string) $resDelOdc->json('message'));

        // Customer uses ODP
        $customer = Customer::factory()->create([
            'pop_id' => $this->popA->id,
            'odc_id' => $odc->id,
            'odp_id' => $odp->id,
        ]);

        // ODP cannot be deleted because Customer uses it
        $resDelOdp = $this->actingAs($this->adminA)
            ->deleteJson(route('odps.destroy', $odp));
        $resDelOdp->assertStatus(422);
        $this->assertStringContainsString('menonaktifkan', (string) $resDelOdp->json('message'));

        // Toggle active works via update
        $this->actingAs($this->adminA)
            ->putJson(route('odcs.update', $odc), ['name' => $odc->name, 'is_active' => false])
            ->assertStatus(200);
        $this->assertFalse($odc->fresh()->is_active);

        $this->actingAs($this->adminA)
            ->putJson(route('odps.update', $odp), ['name' => $odp->name, 'odc_id' => $odc->id, 'is_active' => false])
            ->assertStatus(200);
        $this->assertFalse($odp->fresh()->is_active);

        // Unused item can be deleted
        $unusedOdc = Odc::create([
            'pop_id' => $this->popA->id,
            'name' => 'ODC-UNUSED',
            'is_active' => true,
        ]);

        $this->actingAs($this->adminA)
            ->deleteJson(route('odcs.destroy', $unusedOdc))
            ->assertStatus(200);
        $this->assertDatabaseMissing('odcs', ['id' => $unusedOdc->id]);
    }

    public function test_customer_odc_and_odp_validation(): void
    {
        $odcA = Odc::create(['pop_id' => $this->popA->id, 'name' => 'ODC-A1', 'is_active' => true]);
        $odpA = Odp::create(['pop_id' => $this->popA->id, 'odc_id' => $odcA->id, 'name' => 'ODP-A1', 'is_active' => true]);

        $odcB = Odc::create(['pop_id' => $this->popB->id, 'name' => 'ODC-B1', 'is_active' => true]);
        $odpB = Odp::create(['pop_id' => $this->popB->id, 'odc_id' => $odcB->id, 'name' => 'ODP-B1', 'is_active' => true]);

        // 1. Mismatch POP: Customer POP A with ODC from POP B
        $resMismatchOdc = $this->actingAs($this->adminA)->postJson(route('customers.store'), [
            'name' => 'Pelanggan Baru 1',
            'phone' => '628111222333',
            'address' => 'Jl. Test 1',
            'pop_id' => $this->popA->id,
            'odc_id' => $odcB->id,
            'package' => '20 Mbps',
            'billing_type' => 'Bulanan',
        ]);
        $resMismatchOdc->assertStatus(422)->assertJsonValidationErrors(['odc_id']);

        // 2. Mismatch ODP to ODC: Customer POP A with ODC A and ODP B
        $resMismatchOdp = $this->actingAs($this->adminA)->postJson(route('customers.store'), [
            'name' => 'Pelanggan Baru 2',
            'phone' => '628111222334',
            'address' => 'Jl. Test 2',
            'pop_id' => $this->popA->id,
            'odc_id' => $odcA->id,
            'odp_id' => $odpB->id,
            'package' => '20 Mbps',
            'billing_type' => 'Bulanan',
        ]);
        $resMismatchOdp->assertStatus(422)->assertJsonValidationErrors(['odp_id']);

        // 3. Valid ODC & ODP succeeds
        $resSuccess = $this->actingAs($this->adminA)->postJson(route('customers.store'), [
            'name' => 'Pelanggan Sah',
            'phone' => '628111222335',
            'address' => 'Jl. Test Sah',
            'pop_id' => $this->popA->id,
            'odc_id' => $odcA->id,
            'odp_id' => $odpA->id,
            'package' => '20 Mbps',
            'billing_type' => 'Bulanan',
            'subscribed_at' => now()->format('Y-m-d'),
            'service_status' => 'ACTIVE',
        ]);
        $this->assertTrue(in_array($resSuccess->status(), [200, 201]));
        $this->assertDatabaseHas('customers', [
            'name' => 'Pelanggan Sah',
            'odc_id' => $odcA->id,
            'odp_id' => $odpA->id,
        ]);
    }

    public function test_import_master_data_csv_and_handling_duplicates(): void
    {
        // 1. Template download
        $this->actingAs($this->adminA)
            ->get(route('master-import.template', ['type' => 'odc', 'format' => 'csv']))
            ->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // 2. Import CSV ODC with 1 existing duplicate and 1 new record
        Odc::create(['pop_id' => $this->popA->id, 'name' => 'ODC-EXISTING', 'is_active' => true]);

        $csvContent = "name,latitude,longitude\nODC-EXISTING,0.51,101.44\nODC-BARU-01,0.52,101.45\n";
        $file = UploadedFile::fake()->createWithContent('odc.csv', $csvContent);

        // Upload to get preview
        $uploadRes = $this->actingAs($this->adminA)->post(route('master-import.upload', ['type' => 'odc']), [
            'type' => 'odc',
            'pop_id' => $this->popA->id,
            'file' => $file,
        ]);

        $uploadRes->assertRedirect();
        $location = (string) $uploadRes->headers->get('Location');
        $this->assertStringContainsString('master-import/odc/preview?token=', $location);

        parse_str(parse_url($location, PHP_URL_QUERY) ?? '', $queryParams);
        $token = $queryParams['token'] ?? '';
        $this->assertNotEmpty($token);

        // Preview page loads
        $this->actingAs($this->adminA)
            ->get(route('master-import.preview', ['type' => 'odc', 'token' => $token]))
            ->assertStatus(200);

        // Confirm import
        $confirmRes = $this->actingAs($this->adminA)->post(route('master-import.confirm', ['type' => 'odc']), [
            'token' => $token,
        ]);
        $confirmRes->assertRedirect(route('odcs.index'));
        $confirmRes->assertSessionHas('success');

        // New record created, duplicate skipped
        $this->assertDatabaseHas('odcs', ['pop_id' => $this->popA->id, 'name' => 'ODC-BARU-01']);
        $this->assertEquals(1, Odc::where('pop_id', $this->popA->id)->where('name', 'ODC-EXISTING')->count());

        // 3. Admin A cannot inject records into POP B (controller forces admin pop_id)
        $fileB = UploadedFile::fake()->createWithContent('odc_b.csv', "name,latitude,longitude\nODC-FAIL,0.1,100.1\n");
        $forcedRes = $this->actingAs($this->adminA)->post(route('master-import.upload', ['type' => 'odc']), [
            'type' => 'odc',
            'pop_id' => $this->popB->id,
            'file' => $fileB,
        ]);
        $forcedRes->assertRedirect();
        // Session should be scoped to adminA's POP, not POP B
        $locationB = (string) $forcedRes->headers->get('Location');
        parse_str(parse_url($locationB, PHP_URL_QUERY) ?? '', $qParamsB);
        $previewData = session("master_import_preview_{$qParamsB['token']}");
        $this->assertEquals($this->popA->id, $previewData['pop_id']);
    }
}

