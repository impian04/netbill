<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerApplication;
use App\Models\Pop;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DuplicateCheckerService;
use App\Services\PrivateFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerApplicationController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = auth()->user();

        if (($request->ajax() || $request->expectsJson()) && ! $request->has('_plain')) {
            return $this->dataTableResponse($request, $user);
        }

        $marketingUsers = User::where('role', \App\Enums\Role::MARKETING)
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('pop_id', $user->pop_id))
            ->orderBy('name')
            ->get();

        $pops = $user->isSuperAdmin() ? Pop::orderBy('name')->get() : collect([$user->pop])->filter();

        return view('customer_applications.index', [
            'marketingUsers' => $marketingUsers,
            'pops'           => $pops,
        ]);
    }

    public function show(Request $request, CustomerApplication $application): JsonResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && (int) $application->pop_id !== (int) $user->pop_id) {
            abort(404);
        }

        $coords = null;
        if ($application->latitude !== null && $application->longitude !== null) {
            $coords = "{$application->latitude}, {$application->longitude}";
        }

        $duplicateWarnings = DuplicateCheckerService::check($application, $user);

        return response()->json([
            'id'                 => $application->id,
            'pop_code'           => $application->pop?->code,
            'pop_name'           => $application->pop?->name,
            'marketing_name'     => $application->marketingUser?->name,
            'marketing_phone'    => $application->marketingUser?->phone,
            'name'               => $application->name,
            'phone'              => $application->phone,
            'address'            => $application->address,
            'coordinates'        => $coords,
            'latitude'           => $application->latitude,
            'longitude'          => $application->longitude,
            'package'            => $application->package,
            'billing_type'       => $application->billing_type,
            'notes'              => $application->notes,
            'status'             => $application->status->value,
            'status_label'       => $application->status->label(),
            'status_badge'       => $application->status->badgeColor(),
            'review_note'        => $application->review_note,
            'reviewed_by_name'   => $application->reviewedBy?->name,
            'reviewed_at'        => $application->reviewed_at?->format('d/m/Y H:i'),
            'created_at'         => $application->created_at->format('d/m/Y H:i'),
            'has_ktp'            => ! empty($application->ktp_path),
            'ktp_url'            => ! empty($application->ktp_path) ? route('files.ktp.application', $application) : null,
            'customer_id'        => $application->customer_id,
            'customer_code'      => $application->customer?->customer_code,
            'duplicate_warnings' => $duplicateWarnings,
        ]);
    }

    public function approve(Request $request, CustomerApplication $application): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && (int) $application->pop_id !== (int) $user->pop_id) {
            abort(404);
        }

        // Optional corrections from admin
        $validatedCorrections = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'phone'        => ['sometimes', 'required', 'string'],
            'address'      => ['sometimes', 'required', 'string', 'max:1000'],
            'coordinates'  => ['nullable', 'string'],
            'package'      => ['sometimes', 'required', 'string', 'max:100'],
            'billing_type' => ['nullable', 'string', 'in:Bulanan,Tahunan,Pascabayar,Prabayar'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $customerCode = DB::transaction(function () use ($application, $user, $validatedCorrections) {
            /** @var CustomerApplication $app */
            $app = CustomerApplication::where('id', $application->id)->lockForUpdate()->firstOrFail();

            // Idempotent: already approved
            if ($app->status === ApplicationStatus::APPROVED && $app->customer_id) {
                $existingCust = Customer::find($app->customer_id);
                return $existingCust?->customer_code;
            }

            // Apply corrections if provided
            if (isset($validatedCorrections['name'])) {
                $app->name = $validatedCorrections['name'];
            }
            if (isset($validatedCorrections['phone'])) {
                $app->phone = StoreCustomerRequest::normalizePhone($validatedCorrections['phone']);
            }
            if (isset($validatedCorrections['address'])) {
                $app->address = $validatedCorrections['address'];
            }
            if (isset($validatedCorrections['package'])) {
                $app->package = $validatedCorrections['package'];
            }
            if (isset($validatedCorrections['billing_type'])) {
                $app->billing_type = $validatedCorrections['billing_type'];
            }
            if (isset($validatedCorrections['notes'])) {
                $app->notes = $validatedCorrections['notes'];
            }
            if (isset($validatedCorrections['coordinates'])) {
                $raw = trim($validatedCorrections['coordinates']);
                if ($raw === '') {
                    $app->latitude = null;
                    $app->longitude = null;
                } elseif (str_contains($raw, ',')) {
                    $parts = explode(',', $raw, 2);
                    $lat = (float) trim($parts[0]);
                    $lng = (float) trim($parts[1]);
                    if ($lat >= -11.5 && $lat <= 6.5 && $lng >= 94.0 && $lng <= 141.5) {
                        $app->latitude = $lat;
                        $app->longitude = $lng;
                    }
                }
            }

            // Lock POP and generate next sequential customer code
            /** @var Pop $pop */
            $pop = Pop::where('id', $app->pop_id)->lockForUpdate()->firstOrFail();
            $nextNumber = (int) $pop->last_customer_number + 1;
            $pop->last_customer_number = $nextNumber;
            $pop->save();

            $code = sprintf('JCN-%s-%04d', $pop->code, $nextNumber);

            // Copy KTP to customer directory if exists
            $customerKtpPath = null;
            if ($app->ktp_path && Storage::disk('local')->exists($app->ktp_path)) {
                $ext = pathinfo($app->ktp_path, PATHINFO_EXTENSION) ?: 'jpg';
                $customerKtpPath = 'ktp/' . (string) Str::uuid() . '.' . $ext;
                Storage::disk('local')->copy($app->ktp_path, $customerKtpPath);
            }

            // Create customer
            $customer = Customer::create([
                'pop_id'              => $app->pop_id,
                'customer_code'       => $code,
                'name'                => $app->name,
                'phone'               => $app->phone,
                'address'             => $app->address,
                'latitude'            => $app->latitude,
                'longitude'           => $app->longitude,
                'package'             => $app->package,
                'billing_type'        => $app->billing_type ?: 'Bulanan',
                'registered_via'      => 'marketing',
                'referred_by_user_id' => $app->marketing_user_id,
                'service_status'      => 'PENDING',
                'subscribed_at'       => now()->format('Y-m-d'),
            ]);

            if ($customerKtpPath) {
                $customer->ktp_path = $customerKtpPath;
            }

            $customer->referred_by_user_id = $app->marketing_user_id;
            $customer->save();

            $app->customer_id = $customer->id;
            $app->status = ApplicationStatus::APPROVED;
            $app->reviewed_by = $user->id;
            $app->reviewed_at = now();
            $app->save();

            ActivityLogger::log('application.approve', "Menyetujui pendaftaran pelanggan {$customer->customer_code} ({$customer->name})", $app, $app->pop_id);

            return $customer->customer_code;
        });

        $message = "Pendaftaran disetujui. Pelanggan baru berhasil dibuat: {$customerCode}.";

        if ($request->wantsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => $message,
                'customer_code' => $customerCode,
            ]);
        }

        return redirect()->route('customer-applications.index')->with('success', $message);
    }

    public function requestRevision(Request $request, CustomerApplication $application): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && (int) $application->pop_id !== (int) $user->pop_id) {
            abort(404);
        }

        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ], [
            'review_note.required' => 'Catatan perbaikan wajib diisi.',
        ]);

        if ($application->status === ApplicationStatus::APPROVED) {
            abort(422, 'Pendaftaran yang sudah disetujui tidak dapat diminta perbaikan.');
        }

        $application->status = ApplicationStatus::NEEDS_REVISION;
        $application->review_note = $validated['review_note'];
        $application->reviewed_by = $user->id;
        $application->reviewed_at = now();
        $application->save();

        ActivityLogger::log('application.request_revision', "Meminta perbaikan pendaftaran #{$application->id} ({$application->name})", $application, $application->pop_id);

        $message = 'Permintaan perbaikan telah dikirim ke staf marketing.';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('customer-applications.index')->with('success', $message);
    }

    public function reject(Request $request, CustomerApplication $application): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isSuperAdmin() && (int) $application->pop_id !== (int) $user->pop_id) {
            abort(404);
        }

        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ], [
            'review_note.required' => 'Alasan penolakan wajib diisi.',
        ]);

        if ($application->status === ApplicationStatus::APPROVED) {
            abort(422, 'Pendaftaran yang sudah disetujui tidak dapat ditolak.');
        }

        // Delete KTP file for data minimization
        if ($application->ktp_path) {
            PrivateFileService::delete($application->ktp_path);
            $application->ktp_path = null;
            ActivityLogger::log('ktp.delete', "Menghapus berkas KTP pendaftaran #{$application->id} karena ditolak", $application, $application->pop_id);
        }

        $application->status = ApplicationStatus::REJECTED;
        $application->review_note = $validated['review_note'];
        $application->reviewed_by = $user->id;
        $application->reviewed_at = now();
        $application->save();

        ActivityLogger::log('application.reject', "Menolak pendaftaran #{$application->id} ({$application->name})", $application, $application->pop_id);

        $message = 'Pendaftaran telah ditolak dan berkas KTP dihapus.';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('customer-applications.index')->with('success', $message);
    }

    public function streamKtp(Request $request, CustomerApplication $application): BinaryFileResponse
    {
        $user = auth()->user();
        if (! $user) {
            abort(404);
        }

        $isOwner      = ((int) $user->id === (int) $application->marketing_user_id);
        $isPopAdmin   = ($user->isAdmin() && (int) $user->pop_id === (int) $application->pop_id);
        $isSuperAdmin = $user->isSuperAdmin();

        if (! ($isOwner || $isPopAdmin || $isSuperAdmin)) {
            abort(404);
        }

        if (! $application->ktp_path) {
            abort(404, 'Berkas KTP tidak ditemukan.');
        }

        ActivityLogger::log('ktp.view', "Melihat berkas KTP pendaftaran #{$application->id} ({$application->name})", $application, $application->pop_id);

        return PrivateFileService::streamKtp($application->ktp_path);
    }

    private function dataTableResponse(Request $request, $user): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        $query = CustomerApplication::visibleTo($user)->with(['pop', 'marketingUser']);

        $recordsTotal = (clone $query)->count();

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('marketing_user_id')) {
            $query->where('marketing_user_id', $request->input('marketing_user_id'));
        }
        if ($user->isSuperAdmin() && $request->filled('pop_id')) {
            $query->where('pop_id', $request->input('pop_id'));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
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
            return [
                'id'             => $app->id,
                'created_at'     => $app->created_at->format('d/m/Y H:i'),
                'pop_code'       => $app->pop?->code,
                'marketing_name' => $app->marketingUser?->name,
                'name'           => $app->name,
                'phone'          => $app->phone,
                'address'        => $app->address,
                'package'        => $app->package,
                'billing_type'   => $app->billing_type,
                'status'         => $app->status->value,
                'status_label'   => $app->status->label(),
                'status_badge'   => $app->status->badgeColor(),
                'has_ktp'        => ! empty($app->ktp_path),
                'customer_code'  => $app->customer?->customer_code,
                'urls'           => [
                    'show'             => route('customer-applications.show', $app),
                    'approve'          => route('customer-applications.approve', $app),
                    'request_revision' => route('customer-applications.request-revision', $app),
                    'reject'           => route('customer-applications.reject', $app),
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
