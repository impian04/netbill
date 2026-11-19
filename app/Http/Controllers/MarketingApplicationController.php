<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\StoreMarketingApplicationRequest;
use App\Http\Requests\UpdateMarketingApplicationRequest;
use App\Models\Customer;
use App\Models\CustomerApplication;
use App\Services\ActivityLogger;
use App\Services\PrivateFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingApplicationController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = auth()->user();

        if (($request->ajax() || $request->expectsJson()) && ! $request->has('_plain')) {
            return $this->dataTableResponse($request, $user);
        }

        // Suggestions for package input from existing packages in this POP
        $packages = Customer::where('pop_id', $user->pop_id)
            ->distinct()
            ->pluck('package')
            ->concat(
                CustomerApplication::where('pop_id', $user->pop_id)->distinct()->pluck('package')
            )
            ->filter()
            ->unique()
            ->values();

        return view('marketing.applications.index', [
            'packages' => $packages,
            'pop'      => $user->pop,
        ]);
    }

    public function store(StoreMarketingApplicationRequest $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        $ktpPath = null;
        if ($request->hasFile('ktp_file')) {
            $ktpPath = PrivateFileService::storeKtp($request->file('ktp_file'));
        }

        $application = CustomerApplication::create([
            'pop_id'            => $user->pop_id,
            'marketing_user_id' => $user->id,
            'name'              => $validated['name'],
            'phone'             => $validated['phone'],
            'address'           => $validated['address'],
            'latitude'          => $validated['latitude'] ?? null,
            'longitude'         => $validated['longitude'] ?? null,
            'package'           => $validated['package'],
            'billing_type'      => $validated['billing_type'] ?? 'Bulanan',
            'notes'             => $validated['notes'] ?? null,
            'status'            => ApplicationStatus::SUBMITTED,
        ]);

        if ($ktpPath) {
            $application->ktp_path = $ktpPath;
            $application->save();
            ActivityLogger::log('ktp.upload', "Mengunggah berkas KTP untuk pendaftaran #{$application->id} ({$application->name})", $application, $user->pop_id);
        }

        ActivityLogger::log('application.create', "Membuat pendaftaran pelanggan baru #{$application->id} ({$application->name})", $application, $user->pop_id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pendaftaran pelanggan baru berhasil dikirim dan menunggu verifikasi admin.',
                'data'    => $application,
            ]);
        }

        return redirect()->route('marketing.applications.index')
            ->with('success', 'Pendaftaran pelanggan baru berhasil dikirim.');
    }

    public function show(Request $request, CustomerApplication $application): JsonResponse
    {
        $user = auth()->user();
        if ((int) $application->marketing_user_id !== (int) $user->id) {
            abort(404);
        }

        $coords = null;
        if ($application->latitude !== null && $application->longitude !== null) {
            $coords = "{$application->latitude}, {$application->longitude}";
        }

        $canEdit = in_array($application->status, [ApplicationStatus::SUBMITTED, ApplicationStatus::NEEDS_REVISION], true);

        return response()->json([
            'id'           => $application->id,
            'name'         => $application->name,
            'phone'        => $application->phone,
            'address'      => $application->address,
            'coordinates'  => $coords,
            'latitude'     => $application->latitude,
            'longitude'    => $application->longitude,
            'package'      => $application->package,
            'billing_type' => $application->billing_type,
            'notes'        => $application->notes,
            'status'       => $application->status->value,
            'status_label' => $application->status->label(),
            'status_badge' => $application->status->badgeColor(),
            'review_note'  => $application->review_note,
            'has_ktp'      => ! empty($application->ktp_path),
            'ktp_url'      => ! empty($application->ktp_path) ? route('files.ktp.application', $application) : null,
            'can_edit'     => $canEdit,
        ]);
    }

    public function update(UpdateMarketingApplicationRequest $request, CustomerApplication $application): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $user = auth()->user();

        $previousStatus = $application->status;

        // Handle KTP deletion or replacement
        if ($request->boolean('delete_ktp') && $application->ktp_path) {
            PrivateFileService::delete($application->ktp_path);
            $application->ktp_path = null;
            ActivityLogger::log('ktp.delete', "Menghapus berkas KTP pendaftaran #{$application->id}", $application, $application->pop_id);
        }

        if ($request->hasFile('ktp_file')) {
            if ($application->ktp_path) {
                PrivateFileService::delete($application->ktp_path);
            }
            $application->ktp_path = PrivateFileService::storeKtp($request->file('ktp_file'));
            ActivityLogger::log('ktp.upload', "Memperbarui berkas KTP pendaftaran #{$application->id}", $application, $application->pop_id);
        }

        $application->fill([
            'name'         => $validated['name'],
            'phone'        => $validated['phone'],
            'address'      => $validated['address'],
            'latitude'     => $validated['latitude'] ?? null,
            'longitude'    => $validated['longitude'] ?? null,
            'package'      => $validated['package'],
            'billing_type' => $validated['billing_type'] ?? $application->billing_type,
            'notes'        => $validated['notes'] ?? null,
        ]);

        if ($previousStatus === ApplicationStatus::NEEDS_REVISION) {
            $application->status = ApplicationStatus::SUBMITTED;
            ActivityLogger::log('application.resubmit', "Mengirim ulang perbaikan pendaftaran #{$application->id} ({$application->name})", $application, $application->pop_id);
        } else {
            ActivityLogger::log('application.update', "Memperbarui data pendaftaran #{$application->id} ({$application->name})", $application, $application->pop_id);
        }

        $application->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data pendaftaran berhasil diperbarui.',
                'data'    => $application,
            ]);
        }

        return redirect()->route('marketing.applications.index')
            ->with('success', 'Data pendaftaran berhasil diperbarui.');
    }

    private function dataTableResponse(Request $request, $user): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        $query = CustomerApplication::where('marketing_user_id', $user->id);

        $recordsTotal = (clone $query)->count();

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search
        $rawSearch = $request->input('search');
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('package', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $applications = $query->orderByDesc('id')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $applications->map(function (CustomerApplication $app) {
            $canEdit = in_array($app->status, [ApplicationStatus::SUBMITTED, ApplicationStatus::NEEDS_REVISION], true);

            return [
                'id'           => $app->id,
                'created_at'   => $app->created_at->format('d/m/Y H:i'),
                'name'         => $app->name,
                'phone'        => $app->phone,
                'address'      => $app->address,
                'package'      => $app->package,
                'billing_type' => $app->billing_type,
                'status'       => $app->status->value,
                'status_label' => $app->status->label(),
                'status_badge' => $app->status->badgeColor(),
                'review_note'  => $app->review_note,
                'can_edit'     => $canEdit,
                'urls'         => [
                    'show'   => route('marketing.applications.show', $app),
                    'update' => route('marketing.applications.update', $app),
                ],
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }
}
