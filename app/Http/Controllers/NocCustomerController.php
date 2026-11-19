<?php

namespace App\Http\Controllers;

use App\Http\Resources\NocCustomerResource;
use App\Models\Customer;
use App\Models\Pop;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NocCustomerController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->isNoc())) {
            abort(403, 'Anda tidak memiliki hak akses ke Cari Pelanggan.');
        }

        if ($request->ajax() || $request->wantsJson()) {
            return $this->dataTable($request);
        }

        $pops = Pop::where('is_active', true)->orderBy('name')->get();

        return view('noc.customers.index', compact('pops'));
    }

    private function dataTable(Request $request): JsonResponse
    {
        $query = Customer::with(['pop:id,code,name', 'odc:id,name', 'odp:id,name']);

        if ($request->filled('pop_id') && is_numeric($request->pop_id)) {
            $query->where('pop_id', (int) $request->pop_id);
        }

        $serviceStatus = $request->input('status') ?? $request->input('service_status');
        if ($serviceStatus && in_array($serviceStatus, ['ACTIVE', 'PENDING', 'SUSPENDED', 'TERMINATED', 'INACTIVE'], true)) {
            $query->where('service_status', $serviceStatus);
        }

        $rawSearch = $request->input('q') ?? $request->input('search.value') ?? $request->input('search');
        if (is_array($rawSearch)) {
            $rawSearch = $rawSearch['value'] ?? null;
        }
        $search = is_string($rawSearch) && trim($rawSearch) !== '' ? trim(strip_tags($rawSearch)) : null;

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $recordsTotal = Customer::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $orderColIndex = (int) $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'asc');
        $columns = [0 => 'customer_code', 1 => 'name', 2 => 'pop_id', 3 => 'phone', 4 => 'package', 5 => 'service_status'];
        $orderCol = $columns[$orderColIndex] ?? 'customer_code';

        $customers = $query->orderBy($orderCol, $orderDir)->skip($start)->take($length)->get();

        $data = $customers->map(function (Customer $customer) {
            return [
                'id'             => $customer->id,
                'customer_code'  => $customer->customer_code,
                'name'           => $customer->name,
                'pop_name'       => $customer->pop?->name ?? '-',
                'phone'          => $customer->phone ?? '-',
                'package'        => $customer->package ?? '-',
                'service_status' => $customer->service_status,
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Endpoint API pencarian cepat pelanggan (lintas POP) dengan rate limit 30 req/menit.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->isNoc())) {
            abort(403, 'Akses ditolak.');
        }

        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $customers = Customer::with(['pop:id,code,name', 'odc:id,name', 'odp:id,name'])
            ->where(function ($query) use ($q) {
                $query->where('customer_code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get();

        return NocCustomerResource::collection($customers)->response();
    }

    /**
     * Ambil detail pelanggan untuk NOC.
     * Mencatat activity_logs tanpa data sensitif.
     */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->isNoc())) {
            abort(403, 'Akses ditolak.');
        }

        // Catat activity log pembukaan detail pelanggan oleh NOC tanpa menyertakan data sensitif
        ActivityLogger::log('VIEW_CUSTOMER_DETAIL', 'Detail pelanggan dibuka oleh staf NOC', $customer, $customer->pop_id, $user->id);

        $customer->load([
            'pop:id,code,name',
            'odc:id,name',
            'odp:id,name',
            'invoices' => fn ($q) => $q->orderByDesc('period')->limit(6),
        ]);

        return (new NocCustomerResource($customer))->response();
    }
}

