<?php

namespace App\Http\Controllers;

use App\Filters\CustomerFilter;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Pop;
use App\Services\ActivationCodeService;
use App\Services\ActivityLogger;
use App\Services\PrivateFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CustomerController extends Controller
{
    private const SORT_MAP = [
        0 => 'customer_code',
        1 => 'name',
        2 => 'phone',
        3 => 'package',
        4 => 'billing_type',
        5 => 'service_status',
        6 => 'subscribed_at',
    ];

    public function index(Request $request): View|JsonResponse
    {
        // Handle DataTables server-side request
        if ($request->ajax() || $request->expectsJson()) {
            return $this->dataTableResponse($request);
        }

        $user = auth()->user();
        $pops = $user->isSuperAdmin()
            ? Pop::where('is_active', true)->orderBy('name')->get()
            : collect([$user->pop]);

        $packages     = Customer::visibleTo($user)->select('package')->distinct()->orderBy('package')->pluck('package');
        $billingTypes = Customer::visibleTo($user)->select('billing_type')->distinct()->orderBy('billing_type')->pluck('billing_type');
        $years        = Invoice::visibleTo($user)->selectRaw('YEAR(period) as year')->distinct()->orderByDesc('year')->pluck('year');
        $odcs         = Odc::visibleTo($user)->active()->orderBy('name')->get(['id', 'pop_id', 'name']);
        $odps         = Odp::visibleTo($user)->active()->orderBy('name')->get(['id', 'pop_id', 'odc_id', 'name']);

        $initialPop = $user->isSuperAdmin() ? $pops->first() : $user->pop;
        $nextNum = ($initialPop?->last_customer_number ?? 0) + 1;
        $nextCode = sprintf('JCN-%s-%04d', $initialPop?->code ?? 'KTU', $nextNum);

        return view('customers.index', compact('packages', 'billingTypes', 'years', 'nextCode', 'pops', 'odcs', 'odps'));
    }

    public function store(StoreCustomerRequest $request): JsonResponse|RedirectResponse
    {
        $user  = auth()->user();
        $popId = $user->isSuperAdmin() ? (int) $request->input('pop_id') : (int) $user->pop_id;

        $customer = DB::transaction(function () use ($request, $popId) {
            $pop = Pop::where('id', $popId)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $pop->increment('last_customer_number');
            $customerCode = sprintf('JCN-%s-%04d', $pop->code, $pop->last_customer_number);

            $data = array_merge($request->validated(), [
                'customer_code'  => $customerCode,
                'pop_id'         => $pop->id,
                'registered_via' => 'admin',
                'service_status' => $request->input('service_status', 'PENDING'),
            ]);

            $c = Customer::create($data);

            if ($request->hasFile('ktp_file')) {
                $ktpPath = PrivateFileService::storeKtp($request->file('ktp_file'));
                $c->forceFill(['ktp_path' => $ktpPath])->saveQuietly();
                ActivityLogger::log('ktp.upload', "Mengunggah berkas KTP pelanggan {$c->customer_code} ({$c->name})", $c, $pop->id);
            }

            ActivityLogger::log('customer.create', "Menambahkan pelanggan {$c->customer_code} ({$c->name}) di POP {$pop->code}", $c, $pop->id);
            return $c;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Pelanggan berhasil ditambahkan.',
                'data'    => $customer,
            ], 201);
        }

        return redirect()->route('customers.index')
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function show(Request $request, Customer $customer): View|JsonResponse
    {
        if (! auth()->user()->isSuperAdmin() && $customer->pop_id !== auth()->user()->pop_id) {
            abort(404);
        }

        if ($request->ajax() || $request->expectsJson()) {
            $data = $customer->load(['pop', 'odc', 'odp'])->toArray();
            $data['odc_name'] = $customer->odc?->name;
            $data['odp_name'] = $customer->odp?->name;
            $data['coordinates'] = ($customer->latitude !== null && $customer->longitude !== null)
                ? "{$customer->latitude}, {$customer->longitude}"
                : '';
            $data['has_ktp'] = ! empty($customer->ktp_path);
            $data['ktp_url'] = ! empty($customer->ktp_path) ? route('files.ktp.customer', $customer) : null;

            return response()->json($data);
        }

        $customer->load([
            'pop',
            'odc',
            'odp',
            'referredBy',
            'survey.surveyor',
            'survey.odc',
            'survey.odp',
            'workOrder.technician',
            'workOrder.verifier',
            'workOrder.assigner',
        ]);
        $odcs = Odc::where('pop_id', $customer->pop_id)->active()->orderBy('name')->get(['id', 'pop_id', 'name']);
        $odps = Odp::where('pop_id', $customer->pop_id)->active()->orderBy('name')->get(['id', 'pop_id', 'odc_id', 'name']);

        $years = Invoice::where('customer_id', $customer->id)
            ->selectRaw('YEAR(period) as year')->distinct()->orderByDesc('year')->pluck('year');

        $recentTickets = $customer->tickets()->latest('id')->limit(5)->get();

        $activationCode = session()->pull('activation_code_display');

        return view('customers.show', compact('customer', 'years', 'activationCode', 'odcs', 'odps', 'recentTickets'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse|RedirectResponse
    {
        if (! auth()->user()->isSuperAdmin() && $customer->pop_id !== auth()->user()->pop_id) {
            abort(404);
        }

        $data = $request->safe()->except(['customer_code', 'pop_id', 'ktp_file', 'delete_ktp', 'registered_via', 'referred_by_user_id']);
        $customer->update($data);

        // Handle deletion of KTP
        if ($request->boolean('delete_ktp') && ! $request->hasFile('ktp_file')) {
            if ($customer->ktp_path) {
                PrivateFileService::delete($customer->ktp_path);
                $customer->forceFill(['ktp_path' => null])->saveQuietly();
                ActivityLogger::log('ktp.delete', "Menghapus berkas KTP pelanggan {$customer->customer_code} ({$customer->name})", $customer, $customer->pop_id);
            }
        }

        // Handle upload of new KTP
        if ($request->hasFile('ktp_file')) {
            if ($customer->ktp_path) {
                PrivateFileService::delete($customer->ktp_path);
            }
            $ktpPath = PrivateFileService::storeKtp($request->file('ktp_file'));
            $customer->forceFill(['ktp_path' => $ktpPath])->saveQuietly();
            ActivityLogger::log('ktp.upload', "Mengunggah berkas KTP baru pelanggan {$customer->customer_code} ({$customer->name})", $customer, $customer->pop_id);
        }

        ActivityLogger::log('customer.update', "Memperbarui data pelanggan {$customer->customer_code} ({$customer->name})", $customer, $customer->pop_id);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Data pelanggan berhasil diperbarui.',
                'data'    => $customer,
            ]);
        }

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function streamKtp(Request $request, Customer $customer): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->isSuperAdmin() && (! $user->isAdmin() || (int) $customer->pop_id !== (int) $user->pop_id))) {
            abort(404);
        }

        if (! $customer->ktp_path) {
            abort(404, 'Berkas KTP tidak ditemukan.');
        }

        ActivityLogger::log('ktp.view', "Melihat berkas KTP pelanggan {$customer->customer_code} ({$customer->name})", $customer, $customer->pop_id);

        return PrivateFileService::streamKtp($customer->ktp_path);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse|RedirectResponse
    {
        if (! auth()->user()->isSuperAdmin() && $customer->pop_id !== auth()->user()->pop_id) {
            abort(404);
        }

        $name  = $customer->name;
        $code  = $customer->customer_code;
        $popId = $customer->pop_id;

        $customer->delete();
        ActivityLogger::log('customer.delete', "Menghapus pelanggan {$code} ({$name})", null, $popId);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Pelanggan {$name} berhasil dihapus.",
            ]);
        }

        return redirect()->route('customers.index')
            ->with('success', "Pelanggan {$name} berhasil dihapus.");
    }

    /** Generate & return activation code */
    public function generateActivation(Request $request, Customer $customer): JsonResponse|RedirectResponse
    {
        if (! auth()->user()->isSuperAdmin() && $customer->pop_id !== auth()->user()->pop_id) {
            abort(404);
        }

        $plain  = ActivationCodeService::generate($customer);
        $format = ActivationCodeService::format($plain);

        ActivityLogger::log(
            'activation.create',
            "Membuat kode aktivasi untuk pelanggan {$customer->customer_code} ({$customer->name})",
            $customer,
            $customer->pop_id
        );

        Log::info('Activation code generated', [
            'admin_id'      => auth()->id(),
            'customer_code' => $customer->customer_code,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message'       => 'Kode aktivasi berhasil dibuat.',
                'code'          => $format,
                'customer_name' => $customer->name,
            ]);
        }

        return redirect()->route('customers.show', $customer)
            ->with('activation_code_display', $format)
            ->with('activation_customer_name', $customer->name)
            ->with('success', 'Kode aktivasi berhasil dibuat. Tampilkan ke pelanggan SEKARANG — kode tidak akan muncul lagi.');
    }

    public function suggestCode(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $popId = $user->isSuperAdmin() ? $request->query('pop_id') : $user->pop_id;
        $pop   = $popId ? Pop::find($popId) : ($user->pop ?? Pop::where('is_active', true)->first());

        $nextNum = ($pop?->last_customer_number ?? 0) + 1;
        $code    = sprintf('JCN-%s-%04d', $pop?->code ?? 'KTU', $nextNum);

        return response()->json(['next_code' => $code]);
    }

    private function dataTableResponse(Request $request): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        // Resolve sort column
        $orderColIdx = (int) $request->input('order.0.column', 0);
        $orderDir    = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortColumn  = self::SORT_MAP[$orderColIdx] ?? 'customer_code';

        $rawSearch = $request->input('search');
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);
        if ($search === '') {
            $search = null;
        }

        $filters = [
            'search'         => $search,
            'pop_id'         => $request->input('pop_id'),
            'odc_id'         => $request->input('odc_id'),
            'odp_id'         => $request->input('odp_id'),
            'package'        => $request->input('package'),
            'billing_type'   => $request->input('billing_type'),
            'service_status' => $request->input('service_status'),
            'period'         => $request->input('period'),
            'year'           => $request->input('year'),
            'status'         => $request->input('status'),
            'sort'           => $sortColumn,
            'direction'      => $orderDir,
        ];

        $user = auth()->user();
        $recordsTotal = Customer::visibleTo($user)->count();

        $query = CustomerFilter::apply(
            Customer::query()
                ->select([
                    'id', 'pop_id', 'odc_id', 'odp_id', 'customer_code', 'name', 'phone', 'address',
                    'package', 'billing_type', 'service_status', 'subscribed_at',
                ])
                ->with(['pop:id,code,name', 'odc:id,name', 'odp:id,name'])
                ->withCount('invoices')
                ->withCount(['invoices as unpaid_count' => fn ($q) => $q->where('status', 'UNPAID')]),
            $filters
        );

        $recordsFiltered = (clone $query)->count();

        $customers = $query->offset($start)->limit($length)->get();

        $data = $customers->map(function (Customer $c) {
            return [
                'id'             => $c->id,
                'pop_code'       => $c->pop?->code,
                'pop_name'       => $c->pop?->name,
                'odc_id'         => $c->odc_id,
                'odp_id'         => $c->odp_id,
                'odc_name'       => $c->odc?->name,
                'odp_name'       => $c->odp?->name,
                'customer_code'  => $c->customer_code,
                'name'           => $c->name,
                'phone'          => $c->phone,
                'address'        => $c->address,
                'package'        => $c->package,
                'billing_type'   => $c->billing_type,
                'service_status' => $c->service_status,
                'subscribed_at'  => $c->subscribed_at?->format('Y-m-d'),
                'invoices_count' => $c->invoices_count,
                'unpaid_count'   => $c->unpaid_count,
                'urls'           => [
                    'show'   => route('customers.show', $c),
                    'edit'   => route('customers.show', $c),
                    'update' => route('customers.update', $c),
                    'delete' => route('customers.destroy', $c),
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
