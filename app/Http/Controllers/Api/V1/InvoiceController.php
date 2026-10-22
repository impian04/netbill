<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\InvoiceResource;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    /** GET /api/v1/invoices */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'period'   => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'status'   => ['nullable', 'in:PAID,UNPAID'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'period.regex' => 'Format periode: YYYY-MM',
        ]);

        $customer = $request->user();

        $query = Invoice::where('customer_id', $customer->id)
            ->with('customer:id,customer_code,name,address,package')
            ->orderByDesc('period');

        if ($period = $request->period) {
            $query->where('period', Carbon::createFromFormat('Y-m', $period)->startOfMonth()->format('Y-m-d'));
        }
        if ($status = $request->status) {
            $query->where('status', $status);
        }

        $perPage = min((int) ($request->per_page ?? 15), 50);

        return InvoiceResource::collection($query->paginate($perPage));
    }

    /** GET /api/v1/invoices/{invoice} */
    public function show(Request $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        // IDOR prevention — 404 if not owned by logged-in customer
        if ($invoice->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $invoice->load('customer:id,customer_code,name,address,package');

        return new InvoiceResource($invoice);
    }
}
