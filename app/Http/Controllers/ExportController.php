<?php

namespace App\Http\Controllers;

use App\Exports\CellSanitizer;
use App\Exports\InstallationReportExport;
use App\Exports\TicketExport;
use App\Filters\CustomerFilter;
use App\Filters\InvoiceFilter;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        $filters = $request->only([
            'pop_id', 'package', 'billing_type',
            'period', 'year', 'sort', 'direction',
        ]);
        $filters['search'] = $request->input('q') ?? $request->input('search');
        $filters['service_status'] = $request->input('status') ?? $request->input('service_status');

        ActivityLogger::log('export.customers', 'Mengekspor data pelanggan dengan filter: ' . json_encode(array_filter($filters)));

        $filename = 'export-pelanggan-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8

            fputcsv($out, self::HEADERS_CUSTOMERS);

            CustomerFilter::apply(Customer::query(), $filters)
                ->cursor()
                ->each(function (Customer $c) use ($out) {
                    fputcsv($out, CellSanitizer::sanitizeRow([
                        $c->customer_code,
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
        $filters = $request->only(['pop_id', 'status', 'period', 'year', 'sort', 'direction']);
        $filters['search'] = $request->input('q') ?? $request->input('search');

        ActivityLogger::log('export.invoices', 'Mengekspor data tagihan dengan filter: ' . json_encode(array_filter($filters)));

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
                        $inv->customer->customer_code,
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

    public function marketingFees(Request $request): StreamedResponse
    {
        $user = auth()->user();

        $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ], [
            'month.required' => 'Bulan periode ekspor wajib dipilih.',
            'month.regex'    => 'Format bulan harus YYYY-MM.',
        ]);

        $month = $request->input('month');

        $query = \App\Models\MarketingFee::visibleTo($user)
            ->whereRaw("DATE_FORMAT(eligible_at, '%Y-%m') = ?", [$month])
            ->with(['pop', 'marketingUser.marketingProfile', 'customer']);

        if ($request->filled('marketing_user_id')) {
            $query->where('marketing_user_id', $request->input('marketing_user_id'));
        }
        if ($user->isSuperAdmin() && $request->filled('pop_id')) {
            $query->where('pop_id', $request->input('pop_id'));
        }

        $fees = $query->orderBy('marketing_user_id')->orderBy('eligible_at')->get();

        $filterInfo = "periode {$month}";
        if ($request->filled('marketing_user_id')) {
            $filterInfo .= ", marketing ID: {$request->input('marketing_user_id')}";
        }

        ActivityLogger::log(
            'export.marketing_fees',
            "Mengekspor fee marketing {$filterInfo} (Jumlah data: {$fees->count()})",
            null,
            $user->pop_id
        );

        $spreadsheet = \App\Exports\MarketingFeeExport::build($fees);
        $filename    = "export-fee-marketing-{$month}-" . now()->format('YmdHis') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // ── Ekspor Tiket ────────────────────────────────────────────────────────

    public function tickets(Request $request): StreamedResponse
    {
        $user = auth()->user();

        if (! ($user->isSuperAdmin() || $user->isNoc() || $user->isAdmin())) {
            abort(403, 'Anda tidak memiliki hak akses ekspor tiket.');
        }

        $filters = $request->only([
            'pop_id', 'status', 'category', 'priority', 'channel',
            'sort', 'direction',
        ]);
        $filters['search'] = $request->input('q') ?? $request->input('search');
        $filters['date_from'] = $request->input('from') ?? $request->input('date_from');
        $filters['date_to'] = $request->input('to') ?? $request->input('date_to');

        $query = Ticket::visibleTo($user)
            ->when($request->filled('pop_id') && ! $user->isAdmin(), fn ($q) => $q->where('tickets.pop_id', $filters['pop_id']))
            ->when(! empty($filters['status']),   fn ($q) => $q->where('status',   $filters['status']))
            ->when(! empty($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->when(! empty($filters['priority']), fn ($q) => $q->where('priority', $filters['priority']))
            ->when(! empty($filters['channel']),  fn ($q) => $q->where('channel',  $filters['channel']))
            ->when(! empty($filters['search']),   fn ($q) => $q->where(function ($q2) use ($filters) {
                $s = '%' . $filters['search'] . '%';
                $q2->where('ticket_number', 'like', $s)
                   ->orWhere('reporter_name', 'like', $s)
                   ->orWhere('subject', 'like', $s);
            }))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('opened_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']),   fn ($q) => $q->whereDate('opened_at', '<=', $filters['date_to']));

        $count = (clone $query)->count();

        ActivityLogger::log(
            'export.tickets',
            'Mengekspor tiket dengan filter: ' . json_encode(array_filter($filters)) . " (Jumlah: {$count})",
            null,
            $user->pop_id
        );

        $spreadsheet = TicketExport::build($query);
        $filename    = 'export-tiket-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // ── Ekspor Laporan Pemasangan ────────────────────────────────────────────

    public function installationReport(Request $request): StreamedResponse
    {
        $user = auth()->user();

        if (! ($user->isSuperAdmin() || $user->isAdmin())) {
            abort(403, 'Anda tidak memiliki hak akses ekspor laporan pemasangan.');
        }

        $filters = $request->only(['pop_id']);
        $filters['status'] = $request->input('status');
        $filters['service_status'] = $request->input('status') ?? $request->input('service_status');
        $filters['date_from'] = $request->input('from') ?? $request->input('date_from');
        $filters['date_to'] = $request->input('to') ?? $request->input('date_to');

        $query = Customer::query()
            ->select('customers.*')
            // Scope to admin's POP
            ->when($user->isAdmin(), fn ($q) => $q->where('customers.pop_id', $user->pop_id))
            ->when($user->isSuperAdmin() && $request->filled('pop_id'), fn ($q) => $q->where('customers.pop_id', $request->input('pop_id')))
            // Hanya pelanggan yang punya work order INSTALLATION (laporan pemasangan)
            ->whereHas('workOrder')
            ->when($request->filled('service_status'), fn ($q) => $q->where('service_status', $request->input('service_status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('customers.subscribed_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('customers.subscribed_at', '<=', $request->input('date_to')));

        $count = (clone $query)->count();

        ActivityLogger::log(
            'export.installation_report',
            'Mengekspor laporan pemasangan dengan filter: ' . json_encode(array_filter($filters)) . " (Jumlah: {$count})",
            null,
            $user->pop_id
        );

        $spreadsheet = InstallationReportExport::build($query);
        $filename    = 'export-laporan-pemasangan-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
