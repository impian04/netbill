<?php

namespace App\Exports;

use App\Models\MarketingFee;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MarketingFeeExport
{
    public const HEADERS_DETAIL = [
        'Marketing',
        'POP',
        'ID Pelanggan',
        'Nama Pelanggan',
        'Tanggal Aktif',
        'Nominal Fee',
        'Status',
        'Tanggal Bayar',
        'Metode',
        'Penyedia',
        'Nomor Rekening',
        'Atas Nama',
    ];

    public const HEADERS_SUMMARY = [
        'Nama Marketing',
        'POP',
        'Jumlah Pelanggan',
        'Total Fee (Rp)',
        'Metode',
        'Penyedia',
        'Nomor Rekening',
        'Atas Nama',
    ];

    /**
     * Build the spreadsheet.
     *
     * @param \Illuminate\Support\Collection<int, MarketingFee> $fees
     * @return Spreadsheet
     */
    public static function build($fees): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // ── 1. Sheet "Rincian" ──────────────────────────────────────────
        $sheetDetail = $spreadsheet->getActiveSheet();
        $sheetDetail->setTitle('Rincian');

        $sheetDetail->fromArray(self::HEADERS_DETAIL, null, 'A1');
        $sheetDetail->getStyle('A1:L1')->getFont()->setBold(true);
        $sheetDetail->freezePane('A2');

        // Set account number column (K) and ID Pelanggan (C) as Text
        $maxRow = max(100, $fees->count() + 10);
        $sheetDetail->getStyle("C1:C{$maxRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheetDetail->getStyle("K1:K{$maxRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheetDetail->getStyle("F1:F{$maxRow}")->getNumberFormat()->setFormatCode('#,##0');

        $detailRow = 2;
        foreach ($fees as $fee) {
            $profile = $fee->marketingUser?->marketingProfile;
            $rawAccountNumber = $profile?->account_number ?? '';

            $sheetDetail->setCellValue('A' . $detailRow, CellSanitizer::sanitize($fee->marketingUser?->name ?? ''));
            $sheetDetail->setCellValue('B' . $detailRow, CellSanitizer::sanitize($fee->pop?->code ?? ''));
            $sheetDetail->setCellValueExplicit('C' . $detailRow, CellSanitizer::sanitize($fee->customer?->customer_code ?? ''), DataType::TYPE_STRING);
            $sheetDetail->setCellValue('D' . $detailRow, CellSanitizer::sanitize($fee->customer?->name ?? ''));
            $sheetDetail->setCellValue('E' . $detailRow, $fee->eligible_at ? $fee->eligible_at->format('Y-m-d') : '');
            $sheetDetail->setCellValue('F' . $detailRow, (int) $fee->amount);
            $sheetDetail->setCellValue('G' . $detailRow, $fee->status);
            $sheetDetail->setCellValue('H' . $detailRow, $fee->paid_at ? $fee->paid_at->format('Y-m-d') : '');
            $sheetDetail->setCellValue('I' . $detailRow, CellSanitizer::sanitize($profile?->payout_method ?? ''));
            $sheetDetail->setCellValue('J' . $detailRow, CellSanitizer::sanitize($profile?->provider_name ?? ''));
            $sheetDetail->setCellValueExplicit('K' . $detailRow, CellSanitizer::sanitize($rawAccountNumber), DataType::TYPE_STRING);
            $sheetDetail->setCellValue('L' . $detailRow, CellSanitizer::sanitize($profile?->account_holder ?? ''));

            $detailRow++;
        }

        // Auto-size detail columns
        foreach (range('A', 'L') as $col) {
            $sheetDetail->getColumnDimension($col)->setAutoSize(true);
        }

        // ── 2. Sheet "Ringkasan" ────────────────────────────────────────
        $sheetSummary = $spreadsheet->createSheet();
        $sheetSummary->setTitle('Ringkasan');

        $sheetSummary->fromArray(self::HEADERS_SUMMARY, null, 'A1');
        $sheetSummary->getStyle('A1:H1')->getFont()->setBold(true);
        $sheetSummary->freezePane('A2');

        $sheetSummary->getStyle("D1:D{$maxRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheetSummary->getStyle("G1:G{$maxRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        // Group fees by marketing_user_id
        $grouped = $fees->groupBy('marketing_user_id');
        $summaryRow = 2;

        foreach ($grouped as $mId => $groupFees) {
            $firstFee = $groupFees->first();
            $marketingUser = $firstFee?->marketingUser;
            $profile = $marketingUser?->marketingProfile;
            $rawAccountNumber = $profile?->account_number ?? '';

            $sheetSummary->setCellValue('A' . $summaryRow, CellSanitizer::sanitize($marketingUser?->name ?? ''));
            $sheetSummary->setCellValue('B' . $summaryRow, CellSanitizer::sanitize($firstFee?->pop?->code ?? ''));
            $sheetSummary->setCellValue('C' . $summaryRow, $groupFees->count());
            $sheetSummary->setCellValue('D' . $summaryRow, (int) $groupFees->sum('amount'));
            $sheetSummary->setCellValue('E' . $summaryRow, CellSanitizer::sanitize($profile?->payout_method ?? ''));
            $sheetSummary->setCellValue('F' . $summaryRow, CellSanitizer::sanitize($profile?->provider_name ?? ''));
            $sheetSummary->setCellValueExplicit('G' . $summaryRow, CellSanitizer::sanitize($rawAccountNumber), DataType::TYPE_STRING);
            $sheetSummary->setCellValue('H' . $summaryRow, CellSanitizer::sanitize($profile?->account_holder ?? ''));

            $summaryRow++;
        }

        // Auto-size summary columns
        foreach (range('A', 'H') as $col) {
            $sheetSummary->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}

