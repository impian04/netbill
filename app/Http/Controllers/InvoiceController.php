<?php

namespace App\Http\Controllers;

use App\Filters\InvoiceFilter;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Pop;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    private const SORT_MAP = [
        0 => 'invoice_number',
        1 => 'customer_id',
        2 => 'period',
        3 => 'due_date',
        4 => 'deferred_amount',
        5 => 'status',
        6 => 'paid_at',
    ];

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->expectsJson()) {
            return $this->dataTableResponse($request);
        }

        $user = auth()->user();
        $years = Invoice::visibleTo($user)->selectRaw('YEAR(period) as year')->distinct()->orderByDesc('year')->pluck('year');
        $customers = Customer::visibleTo($user)->select('id', 'customer_code', 'name')->orderBy('customer_code')->get();
        $pops = $user->isSuperAdmin() ? Pop::where('is_active', true)->orderBy('name')->get() : collect([$user->pop]);

        return view('invoices.index', compact('years', 'customers', 'pops'));
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        if (! auth()->user()->isSuperAdmin() && $invoice->customer->pop_id !== auth()->user()->pop_id) {
            abort(404);
        }

        $invoice->load(['customer:id,customer_code,name,pop_id', 'customer.pop:id,code,name']);
        return response()->json($invoice);
    }

    public function store(StoreInvoiceRequest $request, ?Customer $customer = null): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        $targetCustomer = $customer && $customer->exists ? $customer : Customer::findOrFail($request->input('customer_id'));

        if (! $user->isSuperAdmin() && $targetCustomer->pop_id !== $user->pop_id) {
            abort(404);
        }

        $data = $request->validated();

        $invoice = DB::transaction(function () use ($data, $targetCustomer) {
            $inv = $targetCustomer->invoices()->create($data);
            ActivityLogger::log('invoice.create', "Membuat tagihan {$inv->invoice_number} untuk {$targetCustomer->customer_code}", $inv, $targetCustomer->pop_id);
            return $inv;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tagihan berhasil ditambahkan.',
                'data'    => $invoice,
            ], 201);
        }

        return redirect()->route('customers.show', $invoice->customer_id)
            ->with('success', 'Tagihan berhasil ditambahkan.');
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse|RedirectResponse
    {
        if (! auth()->user()->isSuperAdmin() && $invoice->customer->pop_id !== auth()->user()->pop_id) {
            abort(404);
        }

        $oldStatus = $invoice->status;
        $invoice->update($request->validated());

        if ($oldStatus !== $invoice->status) {
            ActivityLogger::log(
                'invoice.update_status',
                "Mengubah status tagihan {$invoice->invoice_number} dari {$oldStatus} ke {$invoice->status}",
                $invoice,
                $invoice->customer->pop_id
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tagihan berhasil diperbarui.',
                'data'    => $invoice,
            ]);
        }

        return redirect()->route('customers.show', $invoice->customer_id)
            ->with('success', 'Tagihan berhasil diperbarui.');
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse|RedirectResponse
    {
        if (! auth()->user()->isSuperAdmin() && $invoice->customer->pop_id !== auth()->user()->pop_id) {
            abort(404);
        }

        $customerId = $invoice->customer_id;
        $popId      = $invoice->customer->pop_id;
        $number     = $invoice->invoice_number;

        $invoice->delete();
        ActivityLogger::log('invoice.delete', "Menghapus tagihan {$number}", null, $popId);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tagihan berhasil dihapus.',
            ]);
        }

        return redirect()->route('customers.show', $customerId)
            ->with('success', 'Tagihan berhasil dihapus.');
    }

    private function dataTableResponse(Request $request): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        $orderColIdx = (int) $request->input('order.0.column', 2);
        $orderDir    = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumn  = self::SORT_MAP[$orderColIdx] ?? 'period';

        $rawSearch = $request->input('search');
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);
        if ($search === '') {
            $search = null;
        }

        $filters = [
            'search'      => $search,
            'pop_id'      => $request->input('pop_id'),
            'customer_id' => $request->input('customer_id'),
            'period'      => $request->input('period'),
            'year'        => $request->input('year'),
            'status'      => $request->input('status'),
            'sort'        => $sortColumn,
            'direction'   => $orderDir,
        ];

        $user = auth()->user();
        $recordsTotal = Invoice::visibleTo($user)->count();

        $query = InvoiceFilter::apply(
            Invoice::query()
                ->select([
                    'id', 'customer_id', 'invoice_number', 'period',
                    'due_date', 'deferred_amount', 'status', 'paid_at', 'payment_reference',
                ])
                ->with(['customer:id,customer_code,name,pop_id', 'customer.pop:id,code,name']),
            $filters
        );

        $recordsFiltered = (clone $query)->count();

        $invoices = $query->offset($start)->limit($length)->get();

        $data = $invoices->map(function (Invoice $inv) {
            return [
                'id'                => $inv->id,
                'customer_id'       => $inv->customer_id,
                'customer_code'     => $inv->customer?->customer_code,
                'customer_name'     => $inv->customer?->name,
                'pop_code'          => $inv->customer?->pop?->code,
                'invoice_number'    => $inv->invoice_number,
                'period'            => $inv->period?->format('Y-m'),
                'due_date'          => $inv->due_date?->format('Y-m-d'),
                'deferred_amount'   => $inv->deferred_amount,
                'status'            => $inv->status,
                'paid_at'           => $inv->paid_at?->format('Y-m-d H:i'),
                'payment_reference' => $inv->payment_reference,
                'urls'              => [
                    'show'   => route('invoices.show', $inv),
                    'update' => route('invoices.update', $inv),
                    'delete' => route('invoices.destroy', $inv),
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
