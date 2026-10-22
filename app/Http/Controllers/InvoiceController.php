<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function create(Customer $customer): View
    {
        return view('invoices.create', compact('customer'));
    }

    public function store(StoreInvoiceRequest $request, Customer $customer): RedirectResponse
    {
        $customer->invoices()->create($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Tagihan berhasil ditambahkan.');
    }

    public function edit(Invoice $invoice): View
    {
        $invoice->load('customer');

        return view('invoices.edit', compact('invoice'));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $invoice->update($request->validated());

        return redirect()->route('customers.show', $invoice->customer_id)
            ->with('success', 'Tagihan berhasil diperbarui.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $customerId = $invoice->customer_id;
        $invoice->delete();

        return redirect()->route('customers.show', $customerId)
            ->with('success', 'Tagihan berhasil dihapus.');
    }
}
