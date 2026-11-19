<?php

namespace App\Http\Controllers;

use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\UpdateSurveyRequest;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Survey;
use App\Models\SurveyItem;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SurveyorSurveyController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        if (! $user || ! $user->isSurveyor()) {
            abort(403, 'Akses khusus Surveyor.');
        }

        $popId = $user->pop_id;
        $status = $request->input('status');
        $rawSearch = $request->input('q') ?? $request->input('search', '');
        $search = trim(strip_tags((string) $rawSearch));

        $query = Survey::query()
            ->visibleTo($user)
            ->with([
                // Keamanan: HANYA kolom publik pelanggan
                'customer:id,customer_code,name,address,phone,latitude,longitude,odc_id,odp_id',
                'odc:id,name',
                'odp:id,name',
                'items.material:id,name,unit',
            ]);

        if ($status && in_array($status, array_column(SurveyStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('customer_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $surveys = $query->latest('id')->paginate(15)->withQueryString();

        $odcs = Odc::where('pop_id', $popId)->active()->orderBy('name')->get(['id', 'name']);
        $odps = Odp::where('pop_id', $popId)->active()->orderBy('name')->get(['id', 'name', 'odc_id']);
        $materials = Material::where('pop_id', $popId)->active()->orderBy('name')->get(['id', 'name', 'unit']);

        return view('surveyor.surveys', [
            'surveys'   => $surveys,
            'odcs'      => $odcs,
            'odps'      => $odps,
            'materials' => $materials,
            'status'    => $status,
            'search'    => $search,
        ]);
    }

    public function show(Survey $survey): JsonResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->isSurveyor() && ! $user->isAdmin() && ! $user->isSuperAdmin())) {
            abort(403);
        }

        // Isolasi surveyor
        if ($user->isSurveyor() && ((int) $survey->surveyor_user_id !== (int) $user->id || (int) $survey->pop_id !== (int) $user->pop_id)) {
            abort(404);
        }

        // Isolasi admin POP
        if ($user->isAdmin() && (int) $survey->pop_id !== (int) $user->pop_id) {
            abort(404);
        }

        $survey->load([
            // Keamanan: HANYA kolom non-sensitif pelanggan
            'customer:id,customer_code,name,address,phone,latitude,longitude,odc_id,odp_id',
            'odc:id,name',
            'odp:id,name',
            'surveyor:id,name',
            'reviewer:id,name',
            'items.material:id,name,unit',
        ]);

        return response()->json([
            'survey' => [
                'id'               => $survey->id,
                'customer_id'      => $survey->customer_id,
                'customer_code'    => $survey->customer?->customer_code,
                'customer_name'    => $survey->customer?->name,
                'customer_address' => $survey->customer?->address,
                'customer_phone'   => $survey->customer?->phone,
                'odc_id'           => $survey->odc_id,
                'odc_name'         => $survey->odc?->name,
                'odp_id'           => $survey->odp_id,
                'odp_name'         => $survey->odp?->name,
                'latitude'         => $survey->latitude,
                'longitude'        => $survey->longitude,
                'coordinates'      => ($survey->latitude !== null && $survey->longitude !== null) ? "{$survey->latitude}, {$survey->longitude}" : '',
                'result'           => $survey->result->value,
                'result_label'     => $survey->result->label(),
                'result_badge'     => $survey->result->badgeColor(),
                'result_note'      => $survey->result_note,
                'status'           => $survey->status->value,
                'status_label'     => $survey->status->label(),
                'status_badge'     => $survey->status->badgeColor(),
                'review_note'      => $survey->review_note,
                'surveyor_name'    => $survey->surveyor?->name,
                'reviewer_name'    => $survey->reviewer?->name,
                'reviewed_at'      => $survey->reviewed_at?->format('d/m/Y H:i'),
                'created_at'       => $survey->created_at?->format('d/m/Y H:i'),
                'is_editable'      => $survey->isEditableBy($user),
                'items'            => $survey->items->map(fn ($it) => [
                    'material_id'   => $it->material_id,
                    'material_name' => $it->material?->name,
                    'quantity'      => $it->quantity,
                    'unit'          => $it->material?->unit,
                ]),
            ],
        ]);
    }

    public function store(StoreSurveyRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Parse coordinates jika disediakan satu string
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        if ($request->filled('coordinates')) {
            $parts = explode(',', (string) $request->input('coordinates'));
            if (count($parts) === 2) {
                $latitude = (float) trim($parts[0]);
                $longitude = (float) trim($parts[1]);
            }
        }

        $survey = DB::transaction(function () use ($request, $user, $latitude, $longitude) {
            // Lock customer row untuk mencegah perebutan bersamaan
            $customer = Customer::where('id', $request->input('customer_id'))->lockForUpdate()->firstOrFail();

            if (Survey::where('customer_id', $customer->id)->exists()) {
                abort(422, 'Pelanggan ini sudah disurvei oleh surveyor lain.');
            }

            $survey = Survey::create([
                'customer_id'      => $customer->id,
                'pop_id'           => $customer->pop_id,
                'surveyor_user_id' => $user->id,
                'odc_id'           => $request->input('odc_id'),
                'odp_id'           => $request->input('odp_id'),
                'latitude'         => $latitude,
                'longitude'        => $longitude,
                'result'           => $request->input('result'),
                'result_note'      => $request->input('result_note'),
                'status'           => SurveyStatus::SUBMITTED,
            ]);

            $itemsData = $request->input('items', []);
            if (is_array($itemsData)) {
                foreach ($itemsData as $row) {
                    if (! empty($row['material_id']) && ! empty($row['quantity'])) {
                        SurveyItem::create([
                            'survey_id'   => $survey->id,
                            'material_id' => (int) $row['material_id'],
                            'quantity'    => (float) $row['quantity'],
                        ]);
                    }
                }
            }

            // Jika hasil Layak, perbarui ODC, ODP, dan koordinat pelanggan
            if ($survey->result === SurveyResult::FEASIBLE) {
                $customer->forceFill([
                    'odc_id'    => $survey->odc_id,
                    'odp_id'    => $survey->odp_id,
                    'latitude'  => $survey->latitude,
                    'longitude' => $survey->longitude,
                ])->save();
            }

            ActivityLogger::log(
                'survey.create',
                "Mengirim survei untuk pelanggan {$customer->customer_code} ({$survey->result->label()})",
                null,
                $survey->pop_id
            );

            return $survey;
        });

        return response()->json([
            'message' => 'Hasil survei berhasil dikirim dan menunggu peninjauan admin.',
            'survey'  => $survey,
        ], 201);
    }

    public function update(UpdateSurveyRequest $request, Survey $survey): JsonResponse
    {
        $user = auth()->user();

        if (! $survey->isEditableBy($user)) {
            return response()->json([
                'message' => "Survei tidak dapat diubah karena berstatus {$survey->status->label()}.",
            ], 422);
        }

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        if ($request->filled('coordinates')) {
            $parts = explode(',', (string) $request->input('coordinates'));
            if (count($parts) === 2) {
                $latitude = (float) trim($parts[0]);
                $longitude = (float) trim($parts[1]);
            }
        }

        DB::transaction(function () use ($request, $survey, $latitude, $longitude) {
            $survey->update([
                'odc_id'      => $request->input('odc_id'),
                'odp_id'      => $request->input('odp_id'),
                'latitude'    => $latitude,
                'longitude'   => $longitude,
                'result'      => $request->input('result'),
                'result_note' => $request->input('result_note'),
                'status'      => SurveyStatus::SUBMITTED, // reset ke SUBMITTED setelah diperbaiki
            ]);

            // Replace items
            $survey->items()->delete();

            $itemsData = $request->input('items', []);
            if (is_array($itemsData)) {
                foreach ($itemsData as $row) {
                    if (! empty($row['material_id']) && ! empty($row['quantity'])) {
                        SurveyItem::create([
                            'survey_id'   => $survey->id,
                            'material_id' => (int) $row['material_id'],
                            'quantity'    => (float) $row['quantity'],
                        ]);
                    }
                }
            }

            // Jika hasil Layak, perbarui ODC, ODP, dan koordinat pelanggan
            if ($survey->result === SurveyResult::FEASIBLE) {
                $customer = $survey->customer;
                if ($customer) {
                    $customer->forceFill([
                        'odc_id'    => $survey->odc_id,
                        'odp_id'    => $survey->odp_id,
                        'latitude'  => $survey->latitude,
                        'longitude' => $survey->longitude,
                    ])->save();
                }
            }

            ActivityLogger::log(
                'survey.update',
                "Memperbarui survei untuk pelanggan {$survey->customer?->customer_code}",
                null,
                $survey->pop_id
            );
        });

        return response()->json([
            'message' => 'Survei berhasil diperbarui.',
            'survey'  => $survey->fresh(['items.material']),
        ]);
    }
}

