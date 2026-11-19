<?php

namespace App\Http\Controllers;

use App\Enums\PhotoStage;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use App\Services\ActivityLogger;
use App\Services\PrivateFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TechnicianTaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        if (! $user || ! $user->isTechnician()) {
            abort(403, 'Akses khusus Teknisi.');
        }

        $status = $request->input('status');
        $search = trim(strip_tags((string) ($request->input('q') ?? $request->input('search', ''))));

        $query = WorkOrder::query()
            ->visibleTo($user)
            ->with([
                // Keamanan data: HANYA kolom non-sensitif, TANPA KTP, tagihan, marketing
                'customer:id,customer_code,name,address,phone,latitude,longitude,odc_id,odp_id,pop_id',
                'customer.odc:id,name',
                'customer.odp:id,name',
                'ticket:id,ticket_number,subject,description,category',
                'survey:id,customer_id,result,result_note',
                'survey.items.material:id,name,unit',
                'photos:id,work_order_id,path,stage,caption,created_at',
            ]);

        if ($status && in_array($status, array_column(WorkOrderStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('customer_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $tasks = $query->latest('id')->paginate(15)->withQueryString();

        return view('technician.tasks', [
            'tasks'  => $tasks,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $user = auth()->user();
        $this->authorizeAccess($workOrder, $user);

        $workOrder->load([
            // Keamanan data: HANYA kolom publik
            'customer:id,customer_code,name,address,phone,latitude,longitude,odc_id,odp_id,pop_id',
            'customer.odc:id,name',
            'customer.odp:id,name',
            'ticket:id,ticket_number,subject,description,category',
            'survey:id,customer_id,result,result_note',
            'survey.items.material:id,name,unit',
            'photos:id,work_order_id,path,stage,caption,created_at',
        ]);

        $dest = ($workOrder->customer?->latitude && $workOrder->customer?->longitude)
            ? "{$workOrder->customer->latitude},{$workOrder->customer->longitude}"
            : urlencode((string) $workOrder->customer?->address);

        return response()->json([
            'work_order' => [
                'id'                 => $workOrder->id,
                'type'               => $workOrder->type->value,
                'type_label'         => $workOrder->type->label(),
                'type_badge'         => $workOrder->type->badgeColor(),
                'ticket_number'      => $workOrder->ticket?->ticket_number,
                'ticket_subject'     => $workOrder->ticket?->subject,
                'ticket_description' => $workOrder->ticket?->description,
                'ticket_category'    => $workOrder->ticket?->category?->label(),
                'customer_id'        => $workOrder->customer_id,
                'customer_code'      => $workOrder->customer?->customer_code,
                'customer_name'      => $workOrder->customer?->name,
                'customer_address'   => $workOrder->customer?->address,
                'customer_phone'     => $workOrder->customer?->phone,
                'odc_name'           => $workOrder->customer?->odc?->name ?? '-',
                'odp_name'           => $workOrder->customer?->odp?->name ?? '-',
                'coordinates'        => ($workOrder->customer?->latitude && $workOrder->customer?->longitude)
                    ? "{$workOrder->customer->latitude}, {$workOrder->customer->longitude}"
                    : '-',
                'route_url'          => "https://www.google.com/maps/dir/?api=1&destination={$dest}",
                'status'             => $workOrder->status->value,
                'status_label'       => $workOrder->status->label(),
                'status_badge'       => $workOrder->status->badgeColor(),
                'scheduled_date'     => $workOrder->scheduled_date?->format('d/m/Y') ?? '-',
                'admin_note'         => $workOrder->admin_note ?: '-',
                'review_note'        => $workOrder->review_note ?: '-',
                'technician_notes'   => $workOrder->technician_notes ?: '',
                'completed_on'       => $workOrder->completed_on?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'is_editable'        => $workOrder->isEditableBy($user),
                'min_photos'         => $workOrder->getMinPhotos(),
                'survey_materials'   => $workOrder->survey?->items->map(fn ($it) => [
                    'material_name' => $it->material?->name ?? '-',
                    'quantity'      => $it->quantity,
                    'unit'          => $it->material?->unit ?? '',
                ]) ?? [],
                'photos'             => $workOrder->photos->map(fn (WorkOrderPhoto $p) => [
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

    public function start(WorkOrder $workOrder): JsonResponse
    {
        $user = auth()->user();
        $this->authorizeAccess($workOrder, $user);

        if ($workOrder->status !== WorkOrderStatus::ASSIGNED) {
            return response()->json([
                'message' => 'Status pekerjaan tidak dapat dimulai lagi.',
            ], 422);
        }

        $workOrder->update(['status' => WorkOrderStatus::IN_PROGRESS]);

        ActivityLogger::log(
            'work_order.start',
            "Teknisi {$user->name} mulai mengerjakan tugas pelanggan {$workOrder->customer?->customer_code}",
            null,
            $workOrder->pop_id
        );

        return response()->json([
            'message' => 'Pekerjaan berhasil dimulai.',
            'status'  => $workOrder->status->value,
        ]);
    }

    public function uploadPhoto(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $user = auth()->user();
        $this->authorizeAccess($workOrder, $user);

        if (! $workOrder->isEditableBy($user)) {
            return response()->json([
                'message' => "Foto tidak dapat diunggah karena status tugas {$workOrder->status->label()}.",
            ], 422);
        }

        if ($workOrder->photos()->count() >= WorkOrder::MAX_PHOTOS) {
            return response()->json([
                'message' => 'Maksimal ' . WorkOrder::MAX_PHOTOS . ' foto per tugas.',
            ], 422);
        }

        $request->validate([
            'photo'   => ['required', 'file', 'max:8192', 'mimes:jpeg,jpg,png,webp'],
            'stage'   => ['required', Rule::enum(PhotoStage::class)],
            'caption' => ['nullable', 'string', 'max:255'],
        ], [
            'photo.required' => 'Berkas foto wajib dilampirkan.',
            'photo.max'      => 'Ukuran foto maksimal 8 MB.',
            'photo.mimes'    => 'Format foto harus JPEG, PNG, atau WebP.',
            'stage.required' => 'Tahapan foto wajib dipilih.',
        ]);

        try {
            $path = PrivateFileService::storeWorkOrderPhoto($request->file('photo'));

            $photo = WorkOrderPhoto::create([
                'work_order_id' => $workOrder->id,
                'path'          => $path,
                'stage'         => $request->input('stage'),
                'caption'       => $request->input('caption'),
                'uploaded_by'   => $user->id,
            ]);

            ActivityLogger::log(
                'work_order.upload_photo',
                "Mengunggah foto tahap {$photo->stage->label()} untuk tugas {$workOrder->customer?->customer_code}",
                null,
                $workOrder->pop_id
            );

            return response()->json([
                'message' => 'Foto berhasil diunggah.',
                'photo'   => [
                    'id'          => $photo->id,
                    'url'         => route('files.work-orders.photo', $photo),
                    'stage'       => $photo->stage->value,
                    'stage_label' => $photo->stage->label(),
                    'caption'     => $photo->caption ?: '-',
                    'created_at'  => $photo->created_at->format('d/m/Y H:i'),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengunggah foto: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function deletePhoto(WorkOrder $workOrder, WorkOrderPhoto $photo): JsonResponse
    {
        $user = auth()->user();
        $this->authorizeAccess($workOrder, $user);

        if (! $workOrder->isEditableBy($user)) {
            return response()->json([
                'message' => "Foto tidak dapat dihapus karena status tugas {$workOrder->status->label()}.",
            ], 422);
        }

        if ((int) $photo->work_order_id !== (int) $workOrder->id) {
            abort(404);
        }

        $photo->delete();

        ActivityLogger::log(
            'work_order.delete_photo',
            "Menghapus foto tugas pelanggan {$workOrder->customer?->customer_code}",
            null,
            $workOrder->pop_id
        );

        return response()->json([
            'message' => 'Foto berhasil dihapus.',
        ]);
    }

    public function submit(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $user = auth()->user();
        $this->authorizeAccess($workOrder, $user);

        if (! $workOrder->isEditableBy($user)) {
            return response()->json([
                'message' => "Tugas tidak dapat dikirim karena status {$workOrder->status->label()}.",
            ], 422);
        }

        $photoCount = $workOrder->photos()->count();
        $minPhotos = $workOrder->getMinPhotos();
        if ($photoCount < $minPhotos) {
            return response()->json([
                'message' => 'Wajib mengunggah minimal ' . $minPhotos . ' foto dokumentasi sebelum mengirim laporan.',
            ], 422);
        }

        $request->validate([
            'technician_notes' => ['nullable', 'string', 'max:1000'],
            'completed_on'     => ['nullable', 'date'],
        ]);

        $completedOn = $request->input('completed_on') ?: now()->format('Y-m-d');

        $workOrder->update([
            'status'           => WorkOrderStatus::SUBMITTED,
            'technician_notes' => $request->input('technician_notes'),
            'completed_on'     => $completedOn,
        ]);

        ActivityLogger::log(
            'work_order.submit',
            "Mengirim laporan pekerjaan pelanggan {$workOrder->customer?->customer_code} untuk verifikasi admin",
            null,
            $workOrder->pop_id
        );

        return response()->json([
            'message' => 'Laporan pekerjaan berhasil dikirim dan menunggu verifikasi admin.',
        ]);
    }

    private function authorizeAccess(WorkOrder $workOrder, $user): void
    {
        if (! $user) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isTechnician() && (int) $workOrder->technician_user_id === (int) $user->id && (int) $workOrder->pop_id === (int) $user->pop_id) {
            return;
        }

        if ($user->isAdmin() && (int) $workOrder->pop_id === (int) $user->pop_id) {
            return;
        }

        abort(404);
    }
}

