<?php

namespace App\Exports;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TicketExport
{
    public const HEADERS_DETAIL = [
        'No. Tiket',
        'Tanggal Dibuka',
        'Kanal',
        'Kategori',
        'Prioritas',
        'Status',
        'Kode Pelanggan',
        'Nama / Pelapor',
        'Telepon',
        'POP',
        'Ditangani Oleh',
        'Respons Pertama (mnt)',
        'Tanggal Selesai',
        'Durasi Penyelesaian (jam)',
        'Subjek',
        'Catatan Penyelesaian',
    ];

    /**
     * Build the ticket export spreadsheet.
     * Accepts a pre-scoped & filtered Eloquent Builder for Ticket.
     *
     * @param  Builder<Ticket>  $query
     */
    public static function build(Builder $query): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // ── Sheet 1: Rincian ─────────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Rincian');
        $sheet->fromArray(self::HEADERS_DETAIL, null, 'A1');
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        // Force text format for code/phone columns to prevent numeric coercion
        $sheet->getStyle('G1:G5000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('I1:I5000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $row = 2;

        // Clone query for detail sheet; load relations via chunk to stay memory-safe
        (clone $query)
            ->with([
                'customer:id,customer_code,phone',
                'pop:id,name',
                'assignee:id,name',
            ])
            ->orderBy('tickets.id')
            ->chunkById(500, function ($tickets) use ($sheet, &$row) {
                foreach ($tickets as $ticket) {
                    $firstResponseMin = ($ticket->opened_at && $ticket->first_response_at)
                        ? round($ticket->opened_at->diffInMinutes($ticket->first_response_at), 1)
                        : '';

                    $resolvedAt = $ticket->resolved_at ?? $ticket->closed_at;
                    $resolutionHr = ($ticket->opened_at && $resolvedAt)
                        ? round($ticket->opened_at->diffInMinutes($resolvedAt) / 60, 2)
                        : '';

                    $customerCode = $ticket->customer?->customer_code ?? '';
                    $phone        = $ticket->reporter_phone ?? $ticket->customer?->phone ?? '';

                    // NO KTP / account numbers / activation codes
                    $sheet->setCellValueExplicit('A' . $row, CellSanitizer::sanitize($ticket->ticket_number), DataType::TYPE_STRING);
                    $sheet->setCellValue('B' . $row, $ticket->opened_at?->format('Y-m-d H:i') ?? '');
                    $sheet->setCellValue('C' . $row, CellSanitizer::sanitize($ticket->channel?->label() ?? ''));
                    $sheet->setCellValue('D' . $row, CellSanitizer::sanitize($ticket->category?->label() ?? ''));
                    $sheet->setCellValue('E' . $row, CellSanitizer::sanitize($ticket->priority?->label() ?? ''));
                    $sheet->setCellValue('F' . $row, CellSanitizer::sanitize($ticket->status?->label() ?? ''));
                    $sheet->setCellValueExplicit('G' . $row, CellSanitizer::sanitize($customerCode), DataType::TYPE_STRING);
                    $sheet->setCellValue('H' . $row, CellSanitizer::sanitize($ticket->reporter_name ?? $ticket->customer?->name ?? ''));
                    $sheet->setCellValueExplicit('I' . $row, CellSanitizer::sanitize($phone), DataType::TYPE_STRING);
                    $sheet->setCellValue('J' . $row, CellSanitizer::sanitize($ticket->pop?->name ?? ''));
                    $sheet->setCellValue('K' . $row, CellSanitizer::sanitize($ticket->assignee?->name ?? 'Belum Ditugaskan'));
                    $sheet->setCellValue('L' . $row, $firstResponseMin);
                    $sheet->setCellValue('M' . $row, $resolvedAt ? $resolvedAt->format('Y-m-d H:i') : '');
                    $sheet->setCellValue('N' . $row, $resolutionHr);
                    $sheet->setCellValue('O' . $row, CellSanitizer::sanitize($ticket->subject));
                    $sheet->setCellValue('P' . $row, CellSanitizer::sanitize($ticket->resolution_note ?? ''));

                    $row++;
                }
            }, 'tickets.id', 'id');

        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Sheet 2: Ringkasan ───────────────────────────────────────────
        $sheetS = $spreadsheet->createSheet()->setTitle('Ringkasan');

        // Build a base sub-query from the same filters (without eager-loads / order)
        $base = (clone $query)->select('tickets.*')->getQuery();

        // --- By Kategori ---
        $catStats = DB::table(DB::raw("({$base->toSql()}) as sub"))
            ->mergeBindings($base)
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        $sheetS->setCellValue('A1', 'Ringkasan per Kategori');
        $sheetS->getStyle('A1')->getFont()->setBold(true);
        $sheetS->fromArray(['Kategori', 'Jumlah'], null, 'A2');
        $sheetS->getStyle('A2:B2')->getFont()->setBold(true);
        $catRow = 3;
        foreach ($catStats as $stat) {
            $label = TicketCategory::tryFrom($stat->category)?->label() ?? $stat->category;
            $sheetS->setCellValue('A' . $catRow, $label);
            $sheetS->setCellValue('B' . $catRow, (int) $stat->cnt);
            $catRow++;
        }

        // --- By Status ---
        $statStats = DB::table(DB::raw("({$base->toSql()}) as sub"))
            ->mergeBindings($base)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $statusRow = $catRow + 2;
        $sheetS->setCellValue('A' . $statusRow, 'Ringkasan per Status');
        $sheetS->getStyle('A' . $statusRow)->getFont()->setBold(true);
        $statusRow++;
        $sheetS->fromArray(['Status', 'Jumlah'], null, 'A' . $statusRow);
        $sheetS->getStyle("A{$statusRow}:B{$statusRow}")->getFont()->setBold(true);
        $statusRow++;
        foreach ($statStats as $stat) {
            $label = TicketStatus::tryFrom($stat->status)?->label() ?? $stat->status;
            $sheetS->setCellValue('A' . $statusRow, $label);
            $sheetS->setCellValue('B' . $statusRow, (int) $stat->cnt);
            $statusRow++;
        }

        // --- By POP ---
        $popStats = DB::table(DB::raw("({$base->toSql()}) as sub"))
            ->mergeBindings($base)
            ->join('pops', 'sub.pop_id', '=', 'pops.id')
            ->selectRaw('
                pops.name as pop_name,
                COUNT(*) as cnt,
                AVG(TIMESTAMPDIFF(MINUTE, sub.opened_at, sub.first_response_at)) as avg_resp_min,
                AVG(TIMESTAMPDIFF(MINUTE, sub.opened_at, COALESCE(sub.resolved_at, sub.closed_at)) / 60) as avg_res_hr
            ')
            ->groupBy('pops.id', 'pops.name')
            ->orderBy('pops.name')
            ->get();

        $popRow = $statusRow + 2;
        $sheetS->setCellValue('A' . $popRow, 'Ringkasan per POP');
        $sheetS->getStyle('A' . $popRow)->getFont()->setBold(true);
        $popRow++;
        $sheetS->fromArray(['POP', 'Jumlah', 'Rata-rata Respons (mnt)', 'Rata-rata Selesai (jam)'], null, 'A' . $popRow);
        $sheetS->getStyle("A{$popRow}:D{$popRow}")->getFont()->setBold(true);
        $popRow++;
        foreach ($popStats as $stat) {
            $sheetS->setCellValue('A' . $popRow, CellSanitizer::sanitize($stat->pop_name));
            $sheetS->setCellValue('B' . $popRow, (int) $stat->cnt);
            $sheetS->setCellValue('C' . $popRow, $stat->avg_resp_min !== null ? round((float) $stat->avg_resp_min, 1) : '');
            $sheetS->setCellValue('D' . $popRow, $stat->avg_res_hr !== null ? round((float) $stat->avg_res_hr, 2) : '');
            $popRow++;
        }

        foreach (range('A', 'D') as $col) {
            $sheetS->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
