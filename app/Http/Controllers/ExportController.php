<?php

namespace App\Http\Controllers;

use App\Exports\CellSanitizer;
use App\Filters\CustomerFilter;
use App\Filters\InvoiceFilter;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    private const HEADERS_CUSTOMERS = [
        'ID Pelanggan', 'Nama', 'No. Telepon', 'Alamat',
        'Tanggal Mulai Berlangganan', 'Jenis Tagihan', 'Paket', 'Status Layanan',
    ];

    private const HEADERS_INVOICES = [
        'No. Tagihan', 'ID Pelanggan', 'Nama Pelanggan', 'Periode',
        'Nominal Ditangguhkan (Rp)', 'Status', 'Tanggal Lunas',
    ];

    public function customers(Request $request): StreamedResponse
    {
        $filters  = $request->only([
            'search', 'package', 'billing_type', 'status', 'service_status',
            'period', 'year', 'sort', 'direction',
        ]);
        $filename = 'export-pelanggan-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8

            fputcsv($out, self::HEADERS_CUSTOMERS);

            CustomerFilter::apply(Customer::query(), $filters)
                ->cursor()
                ->each(function (Customer $c) use ($out) {
                    fputcsv($out, CellSanitizer::sanitizeRow([
                        $c->customer_code,               // plain — no ' prefix (not a number)
                        $c->name,
                        $c->phone ?? '',
                        $c->address,
                        $c->subscribed_at->format('Y-m-d'),
                        $c->billing_type,
                        $c->package,
                        $c->service_status,
                    ]));
                });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function invoices(Request $request): StreamedResponse
    {
        $filters  = $request->only(['search', 'status', 'period', 'year', 'sort', 'direction']);
        $filename = 'export-tagihan-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8

            fputcsv($out, self::HEADERS_INVOICES);

            InvoiceFilter::apply(
                Invoice::query()->with('customer:id,customer_code,name'),
                $filters
            )
                ->cursor()
                ->each(function (Invoice $inv) use ($out) {
                    fputcsv($out, CellSanitizer::sanitizeRow([
                        $inv->invoice_number ?? '',
                        $inv->customer->customer_code,   // plain — no ' prefix
                        $inv->customer->name,
                        bulan_indo($inv->period),
                        $inv->deferred_amount,
                        $inv->status,
                        $inv->paid_at ? $inv->paid_at->format('Y-m-d H:i') : '',
                    ]));
                });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
