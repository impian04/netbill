<?php

namespace App\Http\Controllers;

use App\Filters\CustomerFilter;
use App\Filters\InvoiceFilter;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Pop;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $filters = $request->only(['period', 'year', 'pop_id']);

        // 1. Total customers (scoped to user's pop or filtered pop)
        $totalCustomers = CustomerFilter::apply(Customer::query(), $filters)->count();

        // 2. Aggregate stats — reuse InvoiceFilter untuk konsistensi
        $stats = InvoiceFilter::apply(Invoice::query(), $filters)
            ->selectRaw("
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = 'PAID'   THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'UNPAID' THEN 1 ELSE 0 END) as unpaid_count,
                COALESCE(SUM(CASE WHEN status = 'PAID'   THEN deferred_amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN status = 'UNPAID' THEN deferred_amount ELSE 0 END), 0) as unpaid_amount
            ")
            ->first();

        // 3. Ringkasan per periode (maks 12 periode, oldest→newest untuk chart kiri→kanan)
        $periodSummary = InvoiceFilter::apply(Invoice::query(), $filters)
            ->select('period')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'PAID'   THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'UNPAID' THEN 1 ELSE 0 END) as unpaid_count
            ")
            ->groupBy('period')
            ->orderByDesc('period')
            ->limit(12)
            ->get()
            ->sortBy('period')
            ->values();

        // 4. Tagihan UNPAID terlama (10 teratas)
        $oldestUnpaid = InvoiceFilter::apply(Invoice::query(), array_merge($filters, ['status' => 'UNPAID']))
            ->with(['customer:id,name,customer_code,package,pop_id', 'customer.pop:id,code,name'])
            ->orderBy('period')
            ->limit(10)
            ->get(['id', 'customer_id', 'period', 'deferred_amount', 'status']);

        // 5. Tahun tersedia untuk dropdown filter
        $years = InvoiceFilter::apply(Invoice::query(), $filters)
            ->reorder()
            ->selectRaw('YEAR(period) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        // 6. POPs untuk filter super_admin
        $pops = $user->isSuperAdmin()
            ? Pop::where('is_active', true)->orderBy('name')->get()
            : collect([$user->pop]);

        return view('dashboard.index', compact(
            'totalCustomers',
            'stats',
            'periodSummary',
            'oldestUnpaid',
            'filters',
            'years',
            'pops'
        ));
    }
}
