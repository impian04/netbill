<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ImportTemplateExport
{
    public const HEADERS = [
        'ID Pelanggan', 'Nama', 'Nomor Telepon', 'Alamat',
        'Tanggal Mulai Berlangganan', 'Jenis Tagihan', 'Paket', 'Status Layanan',
        'Periode', 'Nominal Ditangguhkan', 'Status', 'Jatuh Tempo',
        'Latitude', 'Longitude',
    ];

    public static function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // ── 1. Sheet "Data" ──────────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        // Headers
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        // Column formats for all data rows (up to row 1000)
        // customer_code (A) & phone (C) as Text (@)
        $sheet->getStyle('A1:A1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('C1:C1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        // Dates (E, L)
        $sheet->getStyle('E1:E1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('L1:L1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        // Periode (I)
        $sheet->getStyle('I1:I1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        // Nominal (J)
        $sheet->getStyle('J1:J1000')->getNumberFormat()->setFormatCode('#,##0');
        // Latitude & Longitude (M, N)
        $sheet->getStyle('M1:N1000')->getNumberFormat()->setFormatCode('0.0000000');

        // Column widths
        $widths = [
            'A' => 18, 'B' => 24, 'C' => 20, 'D' => 36,
            'E' => 26, 'F' => 16, 'G' => 18, 'H' => 18,
            'I' => 16, 'J' => 22, 'K' => 14, 'L' => 18,
            'M' => 16, 'N' => 16,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // 2 Example rows
        $example1 = [
            'JCN-KTU-0001', 'Budi Santoso', '08123456789', 'Jl. Merdeka No. 10, Kel. Sukamaju',
            '2023-01-15', 'Bulanan', '10 Mbps', 'ACTIVE',
            '2026-10', 220000, 'UNPAID', '2026-10-20',
            0.5071000, 101.4478000,
        ];
        $example2 = [
            'JCN-KTU-0002', 'Siti Rahayu', '08987654321', 'Jl. Pahlawan No. 5, RT 02/03',
            '2022-06-01', 'Bulanan', '20 Mbps', 'ACTIVE',
            '2026-10', 330000, 'PAID', '',
            0.5123000, 101.4512000,
        ];

        self::writeExampleRow($sheet, 2, $example1);
        self::writeExampleRow($sheet, 3, $example2);

        // Data Validation Dropdowns
        // F = Jenis Tagihan (Bulanan, Tahunan)
        $valBilling = new DataValidation();
        $valBilling->setType(DataValidation::TYPE_LIST);
        $valBilling->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $valBilling->setAllowBlank(false);
        $valBilling->setShowDropDown(true);
        $valBilling->setFormula1('"Bulanan,Tahunan"');

        // H = Status Layanan (ACTIVE, PENDING, SUSPENDED, INACTIVE)
        $valService = new DataValidation();
        $valService->setType(DataValidation::TYPE_LIST);
        $valService->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $valService->setAllowBlank(false);
        $valService->setShowDropDown(true);
        $valService->setFormula1('"ACTIVE,PENDING,SUSPENDED,INACTIVE"');

        // K = Status Tagihan (PAID, UNPAID)
        $valStatus = new DataValidation();
        $valStatus->setType(DataValidation::TYPE_LIST);
        $valStatus->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $valStatus->setAllowBlank(false);
        $valStatus->setShowDropDown(true);
        $valStatus->setFormula1('"PAID,UNPAID"');

        for ($r = 2; $r <= 200; $r++) {
            $sheet->getCell("F{$r}")->setDataValidation(clone $valBilling);
            $sheet->getCell("H{$r}")->setDataValidation(clone $valService);
            $sheet->getCell("K{$r}")->setDataValidation(clone $valStatus);
        }

        // ── 2. Sheet "Petunjuk" ──────────────────────────────────────────
        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Petunjuk');

        $guideHeaders = ['Nama Kolom', 'Wajib / Opsional', 'Format / Tipe', 'Keterangan & Contoh'];
        $guide->fromArray($guideHeaders, null, 'A1');
        $guide->getStyle('A1:D1')->getFont()->setBold(true);
        $guide->freezePane('A2');

        $instructions = [
            ['ID Pelanggan', 'Wajib', 'Teks (@)', 'Kode unik pelanggan (contoh: JCN-KTU-0001). Format: JCN-{KODE_POP}-{NOMOR}.'],
            ['Nama', 'Wajib', 'Teks', 'Nama lengkap pelanggan.'],
            ['Nomor Telepon', 'Opsional', 'Teks (@)', 'Nomor HP/WhatsApp (contoh: 08123456789 atau 628123456789). Wajib berformat Teks agar angka 0 di depan tidak hilang.'],
            ['Alamat', 'Wajib', 'Teks', 'Alamat lengkap pemasangan.'],
            ['Tanggal Mulai Berlangganan', 'Wajib', 'Tanggal (YYYY-MM-DD)', 'Tanggal mulai berlangganan (contoh: 2023-01-15 atau 15/01/2023).'],
            ['Jenis Tagihan', 'Wajib', 'Pilihan (Dropdown)', 'Pilih dari daftar dropdown: Bulanan atau Tahunan.'],
            ['Paket', 'Wajib', 'Teks', 'Nama paket internet (contoh: 10 Mbps, 20 Mbps).'],
            ['Status Layanan', 'Opsional', 'Pilihan (Dropdown)', 'Pilih: ACTIVE, PENDING, SUSPENDED, atau INACTIVE. Default: ACTIVE.'],
            ['Periode', 'Wajib', 'Teks', 'Periode tagihan. Format YYYY-MM (contoh: 2026-10) atau nama bulan (contoh: Oktober 2026).'],
            ['Nominal Ditangguhkan', 'Wajib', 'Angka', 'Nominal tagihan dalam Rupiah tanpa titik/koma (contoh: 220000).'],
            ['Status', 'Wajib', 'Pilihan (Dropdown)', 'Status pembayaran tagihan. Pilih: PAID atau UNPAID.'],
            ['Jatuh Tempo', 'Opsional', 'Tanggal (YYYY-MM-DD)', 'Batas waktu pembayaran (contoh: 2026-10-20 atau 20/10/2026). Kosongkan jika tidak ada.'],
            ['Latitude', 'Opsional', 'Angka Desimal', 'Derajat desimal wilayah Indonesia: -11.5 s/d 6.5 (contoh: 0.5071).'],
            ['Longitude', 'Opsional', 'Angka Desimal', 'Derajat desimal wilayah Indonesia: 94.0 s/d 141.5 (contoh: 101.4478).'],
        ];

        $guide->fromArray($instructions, null, 'A2');

        $guideWidths = ['A' => 28, 'B' => 18, 'C' => 24, 'D' => 65];
        foreach ($guideWidths as $col => $w) {
            $guide->getColumnDimension($col)->setWidth($w);
        }

        // Kembalikan sheet aktif ke sheet "Data"
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private static function writeExampleRow($sheet, int $row, array $data): void
    {
        $sheet->setCellValueExplicit("A{$row}", (string) $data[0], DataType::TYPE_STRING);
        $sheet->setCellValue("B{$row}", $data[1]);
        $sheet->setCellValueExplicit("C{$row}", (string) $data[2], DataType::TYPE_STRING);
        $sheet->setCellValue("D{$row}", $data[3]);
        $sheet->setCellValueExplicit("E{$row}", (string) $data[4], DataType::TYPE_STRING);
        $sheet->setCellValue("F{$row}", $data[5]);
        $sheet->setCellValue("G{$row}", $data[6]);
        $sheet->setCellValue("H{$row}", $data[7]);
        $sheet->setCellValueExplicit("I{$row}", (string) $data[8], DataType::TYPE_STRING);
        $sheet->setCellValue("J{$row}", $data[9]);
        $sheet->setCellValue("K{$row}", $data[10]);
        $sheet->setCellValueExplicit("L{$row}", (string) $data[11], DataType::TYPE_STRING);
        if (isset($data[12])) {
            $sheet->setCellValue("M{$row}", $data[12]);
        }
        if (isset($data[13])) {
            $sheet->setCellValue("N{$row}", $data[13]);
        }
    }
}
