<?php

namespace App\Http\Controllers;

use App\Filters\CustomerFilter;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\ActivationCodeService;
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

        $packages     = Customer::select('package')->distinct()->orderBy('package')->pluck('package');
        $billingTypes = Customer::select('billing_type')->distinct()->orderBy('billing_type')->pluck('billing_type');
        $years        = Invoice::selectRaw('YEAR(period) as year')->distinct()->orderByDesc('year')->pluck('year');
        $nextCode     = $this->suggestNextCode('SLX-GS');

        return view('customers.index', compact('packages', 'billingTypes', 'years', 'nextCode'));
    }

    public function store(StoreCustomerRequest $request): JsonResponse|RedirectResponse
    {
        $customer = DB::transaction(function () use ($request) {
            return Customer::create($request->validated());
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
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json($customer);
        }

        $years = Invoice::where('customer_id', $customer->id)
            ->selectRaw('YEAR(period) as year')->distinct()->orderByDesc('year')->pluck('year');

        $activationCode = session()->pull('activation_code_display');

        return view('customers.show', compact('customer', 'years', 'activationCode'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse|RedirectResponse
    {
        $customer->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Data pelanggan berhasil diperbarui.',
                'data'    => $customer,
            ]);
        }

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Request $request, Customer $customer): JsonResponse|RedirectResponse
    {
        $name = $customer->name;
        $customer->delete();

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
        $plain  = ActivationCodeService::generate($customer);
        $format = ActivationCodeService::format($plain);

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
        $prefix = $request->query('prefix', 'SLX-GS');
        return response()->json(['next_code' => $this->suggestNextCode($prefix)]);
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
            'package'        => $request->input('package'),
            'billing_type'   => $request->input('billing_type'),
            'service_status' => $request->input('service_status'),
            'period'         => $request->input('period'),
            'year'           => $request->input('year'),
            'status'         => $request->input('status'),
            'sort'           => $sortColumn,
            'direction'      => $orderDir,
        ];

        // Total count cached singkat (60s)
        $recordsTotal = Cache::remember('dt_customers_total_count', 60, fn () => Customer::count());

        $query = CustomerFilter::apply(
            Customer::query()
                ->select([
                    'id', 'customer_code', 'name', 'phone', 'address',
                    'package', 'billing_type', 'service_status', 'subscribed_at',
                ])
                ->withCount('invoices')
                ->withCount(['invoices as unpaid_count' => fn ($q) => $q->where('status', 'UNPAID')]),
            $filters
        );

        $recordsFiltered = (clone $query)->count();

        $customers = $query->offset($start)->limit($length)->get();

        $data = $customers->map(function (Customer $c) {
            return [
                'id'             => $c->id,
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

    private function suggestNextCode(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        $last   = Customer::where('customer_code', 'like', $prefix . '-%')
            ->orderByDesc('customer_code')
            ->value('customer_code');

        $next = 1;
        if ($last) {
            $parts = explode('-', $last);
            $seq   = end($parts);
            if (is_numeric($seq)) {
                $next = ((int) $seq) + 1;
            }
        }

        return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
