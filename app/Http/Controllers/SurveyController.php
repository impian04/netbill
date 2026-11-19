<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use App\Models\Odc;
use App\Models\Pop;
use App\Models\Survey;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SurveyController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->isAdmin() && ! $user->isSuperAdmin())) {
            abort(403, 'Akses khusus Admin.');
        }

        if ($request->ajax() || $request->wantsJson()) {
            return $this->dataTableResponse($request);
        }

        $popScope = $user->isSuperAdmin() ? null : $user->pop_id;

        $stats = [
            'total'               => Survey::visibleTo($user)->count(),
            'submitted'           => Survey::visibleTo($user)->where('status', SurveyStatus::SUBMITTED)->count(),
            'resurvey_requested'  => Survey::visibleTo($user)->where('status', SurveyStatus::RESURVEY_REQUESTED)->count(),
            'assigned'            => Survey::visibleTo($user)->where('status', SurveyStatus::ASSIGNED)->count(),
            'closed'              => Survey::visibleTo($user)->where('status', SurveyStatus::CLOSED)->count(),
        ];

        $pops = $user->isSuperAdmin() ? Pop::orderBy('name')->get(['id', 'name']) : collect();
        $odcs = Odc::visibleTo($user)->active()->orderBy('name')->get(['id', 'name', 'pop_id']);
        $surveyors = User::where('role', Role::SURVEYOR)
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('pop_id', $user->pop_id))
            ->orderBy('name')
            ->get(['id', 'name', 'pop_id']);

        $technicians = User::where('role', Role::TECHNICIAN)
            ->where('is_active', true)
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('pop_id', $user->pop_id))
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'pop_id']);

        return view('surveys.index', [
            'stats'        => $stats,
            'pops'         => $pops,
            'odcs'         => $odcs,
            'surveyors'    => $surveyors,
            'technicians'  => $technicians,
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    public function show(Survey $survey): JsonResponse
    {
        $user = auth()->user();
        $this->authorizeAccess($survey);

        $survey->load([
            'customer:id,customer_code,name,address,phone,latitude,longitude,odc_id,odp_id,pop_id,service_status',
            'customer.pop:id,name',
            'pop:id,name',
            'odc:id,name',
            'odp:id,name',
            'surveyor:id,name,phone',
            'reviewer:id,name',
            'items.material:id,name,unit',
        ]);

        $dest = ($survey->latitude && $survey->longitude)
            ? "{$survey->latitude},{$survey->longitude}"
            : urlencode((string) $survey->customer?->address);

        return response()->json([
            'survey' => [
                'id'               => $survey->id,
                'customer_id'      => $survey->customer_id,
                'customer_code'    => $survey->customer?->customer_code,
                'customer_name'    => $survey->customer?->name,
                'customer_address' => $survey->customer?->address,
                'customer_phone'   => $survey->customer?->phone,
                'pop_name'         => $survey->pop?->name ?? $survey->customer?->pop?->name,
                'odc_name'         => $survey->odc?->name ?? '-',
                'odp_name'         => $survey->odp?->name ?? '-',
                'latitude'         => $survey->latitude,
                'longitude'        => $survey->longitude,
                'coordinates'      => ($survey->latitude && $survey->longitude) ? "{$survey->latitude}, {$survey->longitude}" : '-',
                'route_url'        => "https://www.google.com/maps/dir/?api=1&destination={$dest}",
                'result'           => $survey->result->value,
                'result_label'     => $survey->result->label(),
                'result_badge'     => $survey->result->badgeColor(),
                'result_note'      => $survey->result_note ?: '-',
                'status'           => $survey->status->value,
                'status_label'     => $survey->status->label(),
                'status_badge'     => $survey->status->badgeColor(),
                'review_note'      => $survey->review_note ?: '-',
                'surveyor_name'    => $survey->surveyor?->name ?? '-',
                'reviewer_name'    => $survey->reviewer?->name ?? '-',
                'reviewed_at'      => $survey->reviewed_at?->format('d/m/Y H:i') ?? '-',
                'created_at'       => $survey->created_at?->format('d/m/Y H:i'),
                'pop_id'           => $survey->pop_id,
                'can_assign'       => $survey->status === SurveyStatus::SUBMITTED && $survey->result === SurveyResult::FEASIBLE,
                'can_review'       => $survey->status === SurveyStatus::SUBMITTED,
                'can_cancel'       => $survey->status !== SurveyStatus::CLOSED,
                'items'            => $survey->items->map(fn ($it) => [
                    'material_name' => $it->material?->name ?? '-',
                    'quantity'      => $it->quantity,
                    'unit'          => $it->material?->unit ?? '',
                ]),
            ],
        ]);
    }

    public function requestResurvey(Request $request, Survey $survey): JsonResponse
    {
        $this->authorizeAccess($survey);

        $request->validate([
            'review_note' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'review_note.required' => 'Catatan survei ulang wajib diisi.',
            'review_note.min'      => 'Catatan survei ulang minimal 5 karakter.',
        ]);

        if ($survey->status !== SurveyStatus::SUBMITTED) {
            return response()->json([
                'message' => 'Hanya survei berstatus Menunggu Peninjauan yang dapat diminta survei ulang.',
            ], 422);
        }

        $user = auth()->user();

        $survey->update([
            'status'      => SurveyStatus::RESURVEY_REQUESTED,
            'review_note' => $request->input('review_note'),
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        ActivityLogger::log(
            'survey.request_resurvey',
            "Meminta survei ulang pelanggan {$survey->customer?->customer_code}: {$request->input('review_note')}",
            null,
            $survey->pop_id
        );

        return response()->json([
            'message' => "Permintaan survei ulang untuk pelanggan '{$survey->customer?->customer_code}' berhasil dikirim.",
        ]);
    }

    public function cancelCustomer(Request $request, Survey $survey): JsonResponse
    {
        $this->authorizeAccess($survey);

        $request->validate([
            'cancel_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'cancel_reason.required' => 'Alasan pembatalan pelanggan wajib diisi.',
            'cancel_reason.min'      => 'Alasan pembatalan minimal 5 karakter.',
        ]);

        $user = auth()->user();
        $reason = $request->input('cancel_reason');

        DB::transaction(function () use ($survey, $user, $reason) {
            // Update customer menjadi INACTIVE
            $customer = $survey->customer;
            if ($customer) {
                $customer->update(['service_status' => 'INACTIVE']);
            }

            // Update status survei menjadi CLOSED
            $survey->update([
                'status'      => SurveyStatus::CLOSED,
                'review_note' => "Dibatalkan: {$reason}",
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            ActivityLogger::log(
                'survey.cancel_customer',
                "Membatalkan pelanggan {$customer?->customer_code} hasil survei: {$reason}",
                null,
                $survey->pop_id
            );
        });

        return response()->json([
            'message' => "Pelanggan '{$survey->customer?->customer_code}' berhasil dibatalkan dan status survei ditutup.",
        ]);
    }

    private function dataTableResponse(Request $request): JsonResponse
    {
        $user = auth()->user();
        $draw = $request->integer('draw', 1);
        $start = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        $rawSearch = $request->input('q') ?? $request->input('search');
        if (is_array($rawSearch)) {
            $rawSearch = $rawSearch['value'] ?? null;
        }
        $search = is_string($rawSearch) && trim($rawSearch) !== '' ? trim(strip_tags($rawSearch)) : '';

        $query = Survey::query()
            ->visibleTo($user)
            ->with([
                'customer:id,customer_code,name,phone,address,pop_id',
                'customer.pop:id,name',
                'odc:id,name',
                'odp:id,name',
                'surveyor:id,name',
            ]);

        // Filters
        if ($request->filled('pop_id') && is_numeric($request->input('pop_id')) && $user->isSuperAdmin()) {
            $query->where('surveys.pop_id', (int) $request->input('pop_id'));
        }

        if ($request->filled('status')) {
            $st = \App\Enums\SurveyStatus::tryFrom($request->input('status'));
            if ($st) {
                $query->where('surveys.status', $st->value);
            }
        }

        if ($request->filled('surveyor_id') && is_numeric($request->input('surveyor_id'))) {
            $query->where('surveys.surveyor_user_id', (int) $request->input('surveyor_id'));
        }

        if ($request->filled('odc_id') && is_numeric($request->input('odc_id'))) {
            $query->where('surveys.odc_id', (int) $request->input('odc_id'));
        }

        $dateFrom = $request->input('from') ?? $request->input('date_from');
        if ($dateFrom && is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $query->whereDate('surveys.created_at', '>=', $dateFrom);
        }

        $dateTo = $request->input('to') ?? $request->input('date_to');
        if ($dateTo && is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $query->whereDate('surveys.created_at', '<=', $dateTo);
        }

        $recordsTotal = Survey::visibleTo($user)->count();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->where('customer_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhereHas('odc', function ($oq) use ($search) {
                    $oq->where('name', 'like', "%{$search}%");
                })->orWhereHas('odp', function ($opq) use ($search) {
                    $opq->where('name', 'like', "%{$search}%");
                })->orWhereHas('surveyor', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                });
            });
        }

        $recordsFiltered = (clone $query)->count();

        $rows = $query->latest('surveys.id')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $rows->map(function (Survey $survey) {
            return [
                'id'            => $survey->id,
                'customer_code' => $survey->customer?->customer_code ?? '-',
                'customer_name' => $survey->customer?->name ?? '-',
                'pop_name'      => $survey->customer?->pop?->name ?? '-',
                'surveyor_name' => $survey->surveyor?->name ?? '-',
                'odc_name'      => $survey->odc?->name ?? '-',
                'odp_name'      => $survey->odp?->name ?? '-',
                'result'        => $survey->result->value,
                'result_label'  => $survey->result->label(),
                'result_badge'  => $survey->result->badgeColor(),
                'status'        => $survey->status->value,
                'status_label'  => $survey->status->label(),
                'status_badge'  => $survey->status->badgeColor(),
                'created_at'    => $survey->created_at?->format('d/m/Y H:i') ?? '-',
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    private function authorizeAccess(Survey $survey): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isAdmin() && (int) $survey->pop_id === (int) $user->pop_id) {
            return;
        }

        abort(404);
    }
}

