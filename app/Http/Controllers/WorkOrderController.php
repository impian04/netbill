<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketUpdateType;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Pop;
use App\Models\Survey;
use App\Models\TicketUpdate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkOrderController extends Controller
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

        $stats = [
            'total'          => WorkOrder::visibleTo($user)->count(),
            'requested'      => WorkOrder::visibleTo($user)->where('status', WorkOrderStatus::REQUESTED)->count(),
            'assigned'       => WorkOrder::visibleTo($user)->where('status', WorkOrderStatus::ASSIGNED)->count(),
            'in_progress'    => WorkOrder::visibleTo($user)->where('status', WorkOrderStatus::IN_PROGRESS)->count(),
            'submitted'      => WorkOrder::visibleTo($user)->where('status', WorkOrderStatus::SUBMITTED)->count(),
            'needs_revision' => WorkOrder::visibleTo($user)->where('status', WorkOrderStatus::NEEDS_REVISION)->count(),
            'verified'       => WorkOrder::visibleTo($user)->where('status', WorkOrderStatus::VERIFIED)->count(),
        ];

        $pops = $user->isSuperAdmin() ? Pop::orderBy('name')->get(['id', 'name']) : collect();
        $technicians = User::where('role', Role::TECHNICIAN)
            ->where('is_active', true)
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('pop_id', $user->pop_id))
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'pop_id']);

        return view('work_orders.index', [
            'stats'        => $stats,
            'pops'         => $pops,
            'technicians'  => $technicians,
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeAccess($workOrder);

        $workOrder->load([
            'customer:id,customer_code,name,address,phone,latitude,longitude,odc_id,odp_id,pop_id,service_status',
            'customer.pop:id,name',
            'customer.odc:id,name',
            'customer.odp:id,name',
            'survey:id,customer_id,result,result_note',
            'survey.items.material:id,name,unit',
            'ticket:id,ticket_number,subject,description,category',
            'technician:id,name,phone',
            'assigner:id,name',
            'verifier:id,name',
            'photos:id,work_order_id,path,stage,caption,created_at',
        ]);

        $dest = ($workOrder->customer?->latitude && $workOrder->customer?->longitude)
            ? "{$workOrder->customer->latitude},{$workOrder->customer->longitude}"
            : urlencode((string) $workOrder->customer?->address);

        return response()->json([
            'work_order' => [
                'id'               => $workOrder->id,
                'type'             => $workOrder->type->value,
                'type_label'       => $workOrder->type->label(),
                'type_badge'       => $workOrder->type->badgeColor(),
                'ticket_number'    => $workOrder->ticket?->ticket_number,
                'ticket_subject'   => $workOrder->ticket?->subject,
                'ticket_description' => $workOrder->ticket?->description,
                'customer_id'      => $workOrder->customer_id,
                'customer_code'    => $workOrder->customer?->customer_code,
                'customer_name'    => $workOrder->customer?->name,
                'customer_address' => $workOrder->customer?->address,
                'customer_phone'   => $workOrder->customer?->phone,
                'pop_name'         => $workOrder->customer?->pop?->name ?? '-',
                'odc_name'         => $workOrder->customer?->odc?->name ?? '-',
                'odp_name'         => $workOrder->customer?->odp?->name ?? '-',
                'coordinates'      => ($workOrder->customer?->latitude && $workOrder->customer?->longitude)
                    ? "{$workOrder->customer->latitude}, {$workOrder->customer->longitude}"
                    : '-',
                'route_url'        => "https://www.google.com/maps/dir/?api=1&destination={$dest}",
                'status'           => $workOrder->status->value,
                'status_label'     => $workOrder->status->label(),
                'status_badge'     => $workOrder->status->badgeColor(),
                'scheduled_date'   => $workOrder->scheduled_date?->format('d/m/Y') ?? '-',
                'scheduled_date_raw' => $workOrder->scheduled_date?->format('Y-m-d') ?? '',
                'completed_on'     => $workOrder->completed_on?->format('d/m/Y') ?? '-',
                'completed_on_raw' => $workOrder->completed_on?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'activated_on'     => $workOrder->activated_on?->format('d/m/Y') ?? '-',
                'admin_note'       => $workOrder->admin_note ?: '-',
                'technician_notes' => $workOrder->technician_notes ?: '-',
                'review_note'      => $workOrder->review_note ?: '-',
                'technician_id'    => $workOrder->technician_user_id,
                'technician_name'  => $workOrder->technician?->name ?? '-',
                'technician_phone' => $workOrder->technician?->phone ?? '-',
                'assigner_name'    => $workOrder->assigner?->name ?? '-',
                'verifier_name'    => $workOrder->verifier?->name ?? '-',
                'verified_at'      => $workOrder->verified_at?->format('d/m/Y H:i') ?? '-',
                'can_assign_repair'=> $workOrder->status === WorkOrderStatus::REQUESTED,
                'can_reject_repair'=> $workOrder->status === WorkOrderStatus::REQUESTED,
                'can_reassign'     => $workOrder->canBeReassigned(),
                'can_verify'       => $workOrder->status === WorkOrderStatus::SUBMITTED && $workOrder->photos->count() >= $workOrder->getMinPhotos(),
                'can_request_revision' => in_array($workOrder->status, [WorkOrderStatus::SUBMITTED, WorkOrderStatus::IN_PROGRESS], true),
                'can_cancel'       => $workOrder->canBeReassigned(),
                'photos_count'     => $workOrder->photos->count(),
                'min_photos'       => $workOrder->getMinPhotos(),
                'survey_materials' => $workOrder->survey?->items->map(fn ($it) => [
                    'material_name' => $it->material?->name ?? '-',
                    'quantity'      => $it->quantity,
                    'unit'          => $it->material?->unit ?? '',
                ]) ?? [],
                'photos'           => $workOrder->photos->map(fn ($p) => [
                    'id'          => $p->id,
                    'url'         => route('files.work-orders.photo', $p),
                    'stage'       => $p->stage->value,
                    'stage_label' => $p->stage->label(),
                    'caption'     => $p->caption ?: '-',
                    'created_at'  => $p->created_at->format('d/m/Y H:i'),
                ]),
            ],
        ]);
    }

    public function assignFromSurvey(Request $request, Survey $survey): JsonResponse
    {
        $user = auth()->user();

        // Validasi akses survei
        if (! $user->isSuperAdmin() && (int) $survey->pop_id !== (int) $user->pop_id) {
            abort(404);
        }

        if ($survey->result !== SurveyResult::FEASIBLE || $survey->status !== SurveyStatus::SUBMITTED) {
            return response()->json([
                'message' => 'Hanya survei berstatus Menunggu Peninjauan dengan hasil Layak yang dapat ditugaskan ke teknisi.',
            ], 422);
        }

        $request->validate([
            'technician_user_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_date'     => ['nullable', 'date'],
            'admin_note'         => ['nullable', 'string', 'max:1000'],
        ], [
            'technician_user_id.required' => 'Teknisi wajib dipilih.',
        ]);

        $technician = User::find($request->input('technician_user_id'));
        if (! $technician || ! $technician->isTechnician() || ! $technician->is_active || (int) $technician->pop_id !== (int) $survey->pop_id) {
            return response()->json([
                'message' => 'Teknisi yang dipilih tidak valid, tidak aktif, atau bukan dari POP yang sama.',
            ], 422);
        }

        $workOrder = DB::transaction(function () use ($survey, $technician, $request, $user) {
            $wo = WorkOrder::updateOrCreate(
                ['installation_key' => $survey->customer_id],
                [
                    'type'               => WorkOrderType::INSTALLATION,
                    'customer_id'        => $survey->customer_id,
                    'installation_key'   => $survey->customer_id,
                    'survey_id'          => $survey->id,
                    'pop_id'             => $survey->pop_id,
                    'technician_user_id' => $technician->id,
                    'status'             => WorkOrderStatus::ASSIGNED,
                    'scheduled_date'     => $request->input('scheduled_date'),
                    'admin_note'         => $request->input('admin_note'),
                    'assigned_by'        => $user->id,
                ]
            );

            $survey->update(['status' => SurveyStatus::ASSIGNED]);

            ActivityLogger::log(
                'work_order.assign',
                "Menugaskan teknisi {$technician->name} untuk instalasi pelanggan {$survey->customer?->customer_code}",
                null,
                $survey->pop_id
            );

            return $wo;
        });

        return response()->json([
            'message'    => "Penugasan teknisi untuk pelanggan '{$survey->customer?->customer_code}' berhasil dibuat.",
            'work_order' => $workOrder,
        ], 201);
    }

    public function reassign(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeAccess($workOrder);

        if (! $workOrder->canBeReassigned()) {
            return response()->json([
                'message' => "Penugasan tidak dapat dialihkan karena berstatus {$workOrder->status->label()}.",
            ], 422);
        }

        $request->validate([
            'technician_user_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_date'     => ['nullable', 'date'],
            'admin_note'         => ['nullable', 'string', 'max:1000'],
        ]);

        $technician = User::find($request->input('technician_user_id'));
        if (! $technician || ! $technician->isTechnician() || ! $technician->is_active || (int) $technician->pop_id !== (int) $workOrder->pop_id) {
            return response()->json([
                'message' => 'Teknisi yang dipilih tidak valid, tidak aktif, atau bukan dari POP yang sama.',
            ], 422);
        }

        $user = auth()->user();

        $workOrder->update([
            'technician_user_id' => $technician->id,
            'scheduled_date'     => $request->input('scheduled_date') ?: $workOrder->scheduled_date,
            'admin_note'         => $request->input('admin_note') ?: $workOrder->admin_note,
            'assigned_by'        => $user->id,
        ]);

        ActivityLogger::log(
            'work_order.reassign',
            "Mengalihkan tugas pelanggan {$workOrder->customer?->customer_code} ke teknisi {$technician->name}",
            null,
            $workOrder->pop_id
        );

        return response()->json([
            'message' => "Tugas berhasil dialihkan ke teknisi '{$technician->name}'.",
        ]);
    }

    public function assignRepair(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeAccess($workOrder);

        if ($workOrder->status !== WorkOrderStatus::REQUESTED) {
            return response()->json([
                'message' => 'Hanya kunjungan perbaikan berstatus Menunggu Penugasan yang dapat ditugaskan.',
            ], 422);
        }

        $request->validate([
            'technician_user_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_date'     => ['nullable', 'date'],
            'admin_note'         => ['nullable', 'string', 'max:1000'],
        ], [
            'technician_user_id.required' => 'Teknisi wajib dipilih.',
        ]);

        $technician = User::find($request->input('technician_user_id'));
        if (! $technician || ! $technician->isTechnician() || ! $technician->is_active || (int) $technician->pop_id !== (int) $workOrder->pop_id) {
            return response()->json([
                'message' => 'Teknisi yang dipilih tidak valid, tidak aktif, atau bukan dari POP yang sama.',
            ], 422);
        }

        $user = auth()->user();

        DB::transaction(function () use ($workOrder, $technician, $request, $user) {
            $workOrder->update([
                'technician_user_id' => $technician->id,
                'status'             => WorkOrderStatus::ASSIGNED,
                'scheduled_date'     => $request->input('scheduled_date'),
                'admin_note'         => $request->input('admin_note') ?: $workOrder->admin_note,
                'assigned_by'        => $user->id,
            ]);

            if ($workOrder->ticket) {
                TicketUpdate::create([
                    'ticket_id'   => $workOrder->ticket->id,
                    'user_id'     => $user->id,
                    'type'        => TicketUpdateType::ASSIGNMENT,
                    'body'        => "Kunjungan perbaikan ditugaskan kepada teknisi {$technician->name} oleh Admin POP.",
                    'created_at'  => now(),
                ]);
            }

            ActivityLogger::log(
                'work_order.assign_repair',
                "Menugaskan teknisi {$technician->name} untuk perbaikan tiket {$workOrder->ticket?->ticket_number}",
                null,
                $workOrder->pop_id
            );
        });

        return response()->json([
            'message' => "Tugas perbaikan berhasil ditugaskan ke teknisi '{$technician->name}'.",
        ]);
    }

    public function rejectRepair(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeAccess($workOrder);

        if ($workOrder->status !== WorkOrderStatus::REQUESTED) {
            return response()->json([
                'message' => 'Hanya kunjungan perbaikan berstatus Menunggu Penugasan yang dapat ditolak.',
            ], 422);
        }

        $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'reason.required' => 'Alasan penolakan kunjungan wajib diisi.',
            'reason.min'      => 'Alasan penolakan minimal 3 karakter.',
        ]);

        $user = auth()->user();
        $reason = $request->input('reason');

        DB::transaction(function () use ($workOrder, $user, $reason) {
            $workOrder->update([
                'status'      => WorkOrderStatus::CANCELLED,
                'review_note' => "Ditolak Admin POP: {$reason}",
                'verified_by' => $user->id,
                'verified_at' => now(),
            ]);

            if ($workOrder->ticket) {
                $workOrder->ticket->update(['status' => TicketStatus::IN_PROGRESS]);

                TicketUpdate::create([
                    'ticket_id'   => $workOrder->ticket->id,
                    'user_id'     => $user->id,
                    'type'        => TicketUpdateType::STATUS_CHANGE,
                    'from_status' => TicketStatus::FIELD_VISIT,
                    'to_status'   => TicketStatus::IN_PROGRESS,
                    'body'        => "Kunjungan teknisi ditolak oleh Admin POP: {$reason}",
                    'created_at'  => now(),
                ]);
            }

            ActivityLogger::log(
                'work_order.reject_repair',
                "Menolak kunjungan perbaikan tiket {$workOrder->ticket?->ticket_number}: {$reason}",
                null,
                $workOrder->pop_id
            );
        });

        return response()->json([
            'message' => 'Permintaan kunjungan teknisi berhasil ditolak dan status tiket dikembalikan ke Sedang Diproses.',
        ]);
    }

    public function verify(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeAccess($workOrder);

        if ($workOrder->status !== WorkOrderStatus::SUBMITTED) {
            return response()->json([
                'message' => 'Hanya pekerjaan berstatus Menunggu Verifikasi yang dapat diverifikasi.',
            ], 422);
        }

        $minPhotos = $workOrder->getMinPhotos();
        if ($workOrder->photos()->count() < $minPhotos) {
            return response()->json([
                'message' => 'Tidak dapat diverifikasi: foto dokumentasi kurang dari minimal ' . $minPhotos . ' foto.',
            ], 422);
        }

        $request->validate([
            'activated_on' => ['nullable', 'date'],
        ]);

        $user = auth()->user();
        $activatedOn = $request->input('activated_on') ?: ($workOrder->completed_on?->format('Y-m-d') ?: now()->format('Y-m-d'));

        DB::transaction(function () use ($workOrder, $user, $activatedOn) {
            // Update Work Order
            $workOrder->update([
                'status'       => WorkOrderStatus::VERIFIED,
                'verified_by'  => $user->id,
                'verified_at'  => now(),
                'activated_on' => $workOrder->isInstallation() ? $activatedOn : null,
            ]);

            if ($workOrder->isInstallation()) {
                // Update Customer lewat model Eloquent agar event saved terpicu (marketing fee dsb.)
                $customer = $workOrder->customer;
                if ($customer) {
                    $customer->service_status = 'ACTIVE';
                    $customer->subscribed_at  = $activatedOn;
                    $customer->save();
                }

                ActivityLogger::log(
                    'work_order.verify',
                    "Memverifikasi instalasi dan mengaktifkan pelanggan {$customer?->customer_code} pada tanggal {$activatedOn}",
                    null,
                    $workOrder->pop_id
                );
            } else {
                // Perbaikan (REPAIR): TIDAK mengubah status layanan pelanggan, TIDAK mengubah subscribed_at, TIDAK membuat fee
                if ($workOrder->ticket) {
                    $workOrder->ticket->update(['status' => TicketStatus::IN_PROGRESS]);

                    TicketUpdate::create([
                        'ticket_id'   => $workOrder->ticket->id,
                        'user_id'     => $user->id,
                        'type'        => TicketUpdateType::STATUS_CHANGE,
                        'from_status' => TicketStatus::FIELD_VISIT,
                        'to_status'   => TicketStatus::IN_PROGRESS,
                        'body'        => 'Kunjungan selesai',
                        'created_at'  => now(),
                    ]);
                }

                ActivityLogger::log(
                    'work_order.verify_repair',
                    "Memverifikasi kunjungan perbaikan teknisi untuk pelanggan {$workOrder->customer?->customer_code}",
                    null,
                    $workOrder->pop_id
                );
            }
        });

        $customer = $workOrder->fresh()->customer;

        $msg = $workOrder->isInstallation()
            ? "Pelanggan '{$customer?->customer_code}' berhasil diverifikasi dan status layanan telah AKTIF."
            : "Kunjungan perbaikan untuk pelanggan '{$customer?->customer_code}' berhasil diverifikasi. Tiket kembali ke status Sedang Diproses.";

        return response()->json([
            'message'               => $msg,
            'customer_code'         => $customer?->customer_code,
            'customer_show_url'     => $customer ? route('customers.show', $customer) : null,
        ]);
    }

    public function requestRevision(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeAccess($workOrder);

        $request->validate([
            'review_note' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'review_note.required' => 'Catatan perbaikan wajib diisi.',
            'review_note.min'      => 'Catatan perbaikan minimal 5 karakter.',
        ]);

        $user = auth()->user();

        $workOrder->update([
            'status'      => WorkOrderStatus::NEEDS_REVISION,
            'review_note' => $request->input('review_note'),
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        ActivityLogger::log(
            'work_order.request_revision',
            "Meminta perbaikan instalasi pelanggan {$workOrder->customer?->customer_code}: {$request->input('review_note')}",
            null,
            $workOrder->pop_id
        );

        return response()->json([
            'message' => "Permintaan perbaikan untuk pelanggan '{$workOrder->customer?->customer_code}' berhasil dikirim ke teknisi.",
        ]);
    }

    public function cancel(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeAccess($workOrder);

        if (! $workOrder->canBeReassigned()) {
            return response()->json([
                'message' => 'Penugasan yang sudah terverifikasi tidak dapat dibatalkan.',
            ], 422);
        }

        $request->validate([
            'cancel_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'cancel_reason.required' => 'Alasan pembatalan penugasan wajib diisi.',
            'cancel_reason.min'      => 'Alasan pembatalan minimal 5 karakter.',
        ]);

        $user = auth()->user();
        $reason = $request->input('cancel_reason');

        DB::transaction(function () use ($workOrder, $user, $reason) {
            $workOrder->update([
                'status'      => WorkOrderStatus::CANCELLED,
                'review_note' => "Dibatalkan: {$reason}",
                'verified_by' => $user->id,
                'verified_at' => now(),
            ]);

            // Kembalikan status survei ke SUBMITTED agar bisa ditugaskan ulang
            $survey = $workOrder->survey;
            if ($survey) {
                $survey->update(['status' => SurveyStatus::SUBMITTED]);
            }

            ActivityLogger::log(
                'work_order.cancel',
                "Membatalkan penugasan pelanggan {$workOrder->customer?->customer_code}: {$reason}",
                null,
                $workOrder->pop_id
            );
        });

        return response()->json([
            'message' => "Penugasan pelanggan '{$workOrder->customer?->customer_code}' dibatalkan. Status survei dikembalikan ke antrean penugasan.",
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

        $query = WorkOrder::query()
            ->visibleTo($user)
            ->with([
                'customer:id,customer_code,name,phone,address,pop_id,service_status',
                'customer.pop:id,name',
                'technician:id,name,phone',
                'ticket:id,ticket_number',
                'photos:id,work_order_id',
            ]);

        // Filters
        if ($request->filled('type') && in_array($request->input('type'), ['INSTALLATION', 'REPAIR'], true)) {
            $query->where('work_orders.type', $request->input('type'));
        }

        if ($request->filled('pop_id') && is_numeric($request->input('pop_id')) && $user->isSuperAdmin()) {
            $query->where('work_orders.pop_id', (int) $request->input('pop_id'));
        }

        if ($request->filled('status')) {
            $st = \App\Enums\WorkOrderStatus::tryFrom($request->input('status'));
            if ($st) {
                $query->where('work_orders.status', $st->value);
            }
        }

        if ($request->filled('technician_id') && is_numeric($request->input('technician_id'))) {
            $query->where('work_orders.technician_user_id', (int) $request->input('technician_id'));
        }

        $scheduledDate = $request->input('date') ?? $request->input('scheduled_date');
        if ($scheduledDate && is_string($scheduledDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledDate)) {
            $query->whereDate('work_orders.scheduled_date', $scheduledDate);
        }

        $recordsTotal = WorkOrder::visibleTo($user)->count();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->where('customer_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhereHas('technician', function ($tq) use ($search) {
                    $tq->where('name', 'like', "%{$search}%");
                });
            });
        }

        $recordsFiltered = (clone $query)->count();

        $rows = $query->latest('work_orders.id')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $rows->map(function (WorkOrder $wo) {
            return [
                'id'              => $wo->id,
                'type'            => $wo->type->value,
                'type_label'      => $wo->type->label(),
                'type_badge'      => $wo->type->badgeColor(),
                'ticket_number'   => $wo->ticket?->ticket_number,
                'customer_code'   => $wo->customer?->customer_code ?? '-',
                'customer_name'   => $wo->customer?->name ?? '-',
                'pop_name'        => $wo->customer?->pop?->name ?? '-',
                'technician_name' => $wo->technician?->name ?? 'Belum Ditugaskan',
                'scheduled_date'  => $wo->scheduled_date?->format('d/m/Y') ?? '-',
                'status'          => $wo->status->value,
                'status_label'    => $wo->status->label(),
                'status_badge'    => $wo->status->badgeColor(),
                'photos_count'    => $wo->photos->count(),
                'completed_on'    => $wo->completed_on?->format('d/m/Y') ?? '-',
                'created_at'      => $wo->created_at?->format('d/m/Y H:i') ?? '-',
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    private function authorizeAccess(WorkOrder $workOrder): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isAdmin() && (int) $workOrder->pop_id === (int) $user->pop_id) {
            return;
        }

        abort(404);
    }
}

