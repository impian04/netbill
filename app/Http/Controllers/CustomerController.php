<?php

namespace App\Http\Controllers;

use App\Filters\CustomerFilter;
use App\Filters\InvoiceFilter;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\ActivationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only([
            'search', 'period', 'year', 'status', 'package',
            'billing_type', 'service_status', 'sort', 'direction',
        ]);

        $customers = CustomerFilter::apply(
            Customer::query()
                ->withCount('invoices')
                ->withCount(['invoices as unpaid_count' => fn ($q) => $q->where('status', 'UNPAID')]),
            $filters
        )->paginate(15)->withQueryString();

        $packages     = Customer::select('package')->distinct()->orderBy('package')->pluck('package');
        $billingTypes = Customer::select('billing_type')->distinct()->orderBy('billing_type')->pluck('billing_type');
        $years        = Invoice::selectRaw('YEAR(period) as year')->distinct()->orderByDesc('year')->pluck('year');

        return view('customers.index', compact('customers', 'filters', 'packages', 'billingTypes', 'years'));
    }

    public function create(): View
    {
        $packages     = Customer::select('package')->distinct()->orderBy('package')->pluck('package');
        $billingTypes = Customer::select('billing_type')->distinct()->orderBy('billing_type')->pluck('billing_type');
        $nextCode     = $this->suggestNextCode('SLX-GS');

        return view('customers.create', compact('packages', 'billingTypes', 'nextCode'));
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            Customer::create($request->validated());
        });

        return redirect()->route('customers.index')
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function show(Request $request, Customer $customer): View
    {
        $filters  = $request->only(['status', 'period', 'year', 'sort', 'direction']);

        $invoices = InvoiceFilter::apply(
            Invoice::where('customer_id', $customer->id),
            $filters
        )->paginate(10)->withQueryString();

        $years = Invoice::where('customer_id', $customer->id)
            ->selectRaw('YEAR(period) as year')->distinct()->orderByDesc('year')->pluck('year');

        $activationCode = session()->pull('activation_code_display');

        return view('customers.show', compact('customer', 'invoices', 'filters', 'years', 'activationCode'));
    }

    public function edit(Customer $customer): View
    {
        $packages     = Customer::select('package')->distinct()->orderBy('package')->pluck('package');
        $billingTypes = Customer::select('billing_type')->distinct()->orderBy('billing_type')->pluck('billing_type');

        return view('customers.edit', compact('customer', 'packages', 'billingTypes'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', "Pelanggan {$customer->name} berhasil dihapus.");
    }

    /** Generate & return activation code (flash ONCE) */
    public function generateActivation(Customer $customer): RedirectResponse
    {
        $plain = ActivationCodeService::generate($customer);

        Log::info('Activation code generated', [
            'admin_id'      => auth()->id(),
            'customer_code' => $customer->customer_code,
        ]);

        return redirect()->route('customers.show', $customer)
            ->with('activation_code_display', ActivationCodeService::format($plain))
            ->with('success', 'Kode aktivasi berhasil dibuat. Tampilkan ke pelanggan SEKARANG — kode tidak akan muncul lagi.');
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
