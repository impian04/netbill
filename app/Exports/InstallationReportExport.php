<?php

namespace App\Exports;

use App\Models\Customer;
use App\Models\Pop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InstallationReportExport
{
    public const HEADERS_INSTALLATION = [
        'Kode Pelanggan',
        'Nama Pelanggan',
        'POP',
        'Asal Pendaftaran',
        'Marketing',
        'Tanggal Daftar',
        'Tanggal Survei',
        'Hasil Survei',
        'ODC',
        'ODP',
        'Teknisi',
        'Tanggal Selesai Instalasi',
        'Tanggal Aktif',
        'Lama Proses (hari)',
    ];

    public const HEADERS_MATERIALS = [
        'Kode Pelanggan',
        'Nama Barang',
        'Satuan',
        'Jumlah',
    ];

    /**
     * Build the installation report spreadsheet.
     *
     * @param  Builder<Customer>  $query  Pre-scoped & filtered Customer query.
     */
    public static function build(Builder $query): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // ── Sheet 1: Pemasangan ──────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Pemasangan');
        $sheet->fromArray(self::HEADERS_INSTALLATION, null, 'A1');
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        // Force text for Kode Pelanggan (A) — no numeric coercion
        $sheet->getStyle('A1:A5000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('N1:N5000')->getNumberFormat()->setFormatCode('#,##0.0');

        $row        = 2;
        $customerIds = [];

        (clone $query)
            ->with([
                'pop:id,name',
                'odc:id,name',
                'odp:id,name',
                'referredBy:id,name',   // marketing
                'survey:id,customer_id,created_at,result,reviewed_at',
                'survey.odc:id,name',
                'survey.odp:id,name',
                'workOrder:id,customer_id,technician_user_id,completed_on,activated_on',
                'workOrder.technician:id,name',
            ])
            ->orderBy('customers.id')
            ->chunkById(500, function ($customers) use ($sheet, &$row, &$customerIds) {
                foreach ($customers as $c) {
                    $customerIds[] = $c->id;

                    $survey    = $c->survey;
                    $wo        = $c->workOrder;

                    $registeredAt = $c->subscribed_at ? $c->subscribed_at->format('Y-m-d') : '';
                    $surveyedAt   = $survey?->created_at ? $survey->created_at->format('Y-m-d') : '';
                    $completedOn  = $wo?->completed_on ? $wo->completed_on->format('Y-m-d') : '';
                    $activatedOn  = $wo?->activated_on ? $wo->activated_on->format('Y-m-d') : '';

                    // ODC/ODP: prefer survey-level, fallback to customer-level
                    $odcName = $survey?->odc?->name ?? $c->odc?->name ?? '';
                    $odpName = $survey?->odp?->name ?? $c->odp?->name ?? '';

                    // Lama proses: subscribed_at → activated_on
                    $lamaProses = '';
                    if ($c->subscribed_at && $wo?->activated_on) {
                        $lamaProses = $c->subscribed_at->diffInDays($wo->activated_on);
                    }

                    // NO KTP / activation codes / account numbers
                    $sheet->setCellValueExplicit('A' . $row, CellSanitizer::sanitize($c->customer_code), DataType::TYPE_STRING);
                    $sheet->setCellValue('B' . $row, CellSanitizer::sanitize($c->name));
                    $sheet->setCellValue('C' . $row, CellSanitizer::sanitize($c->pop?->name ?? ''));
                    $sheet->setCellValue('D' . $row, CellSanitizer::sanitize($c->registered_via ?? ''));
                    $sheet->setCellValue('E' . $row, CellSanitizer::sanitize($c->referredBy?->name ?? ''));
                    $sheet->setCellValue('F' . $row, $registeredAt);
                    $sheet->setCellValue('G' . $row, $surveyedAt);
                    $sheet->setCellValue('H' . $row, CellSanitizer::sanitize($survey?->result?->label() ?? ''));
                    $sheet->setCellValue('I' . $row, CellSanitizer::sanitize($odcName));
                    $sheet->setCellValue('J' . $row, CellSanitizer::sanitize($odpName));
                    $sheet->setCellValue('K' . $row, CellSanitizer::sanitize($wo?->technician?->name ?? ''));
                    $sheet->setCellValue('L' . $row, $completedOn);
                    $sheet->setCellValue('M' . $row, $activatedOn);
                    $sheet->setCellValue('N' . $row, $lamaProses);

                    $row++;
                }
            }, 'customers.id', 'id');

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Sheet 2: Barang ──────────────────────────────────────────────
        $sheetM = $spreadsheet->createSheet()->setTitle('Barang');
        $sheetM->fromArray(self::HEADERS_MATERIALS, null, 'A1');
        $sheetM->getStyle('A1:D1')->getFont()->setBold(true);
        $sheetM->freezePane('A2');
        $sheetM->getStyle('A1:A5000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheetM->getStyle('D1:D5000')->getNumberFormat()->setFormatCode('#,##0.##');

        $matRow = 2;

        if (! empty($customerIds)) {
            // Load survey items for all exported customers
            $items = DB::table('survey_items')
                ->join('surveys', 'surveys.id', '=', 'survey_items.survey_id')
                ->join('customers', 'customers.id', '=', 'surveys.customer_id')
                ->join('materials', 'materials.id', '=', 'survey_items.material_id')
                ->whereIn('surveys.customer_id', $customerIds)
                ->select(
                    'customers.customer_code',
                    'materials.name as material_name',
                    'materials.unit',
                    'survey_items.quantity'
                )
                ->orderBy('customers.id')
                ->orderBy('materials.name')
                ->cursor();

            foreach ($items as $item) {
                $sheetM->setCellValueExplicit('A' . $matRow, CellSanitizer::sanitize($item->customer_code), DataType::TYPE_STRING);
                $sheetM->setCellValue('B' . $matRow, CellSanitizer::sanitize($item->material_name));
                $sheetM->setCellValue('C' . $matRow, CellSanitizer::sanitize($item->unit));
                $sheetM->setCellValue('D' . $matRow, (float) $item->quantity);
                $matRow++;
            }
        }

        foreach (range('A', 'D') as $col) {
            $sheetM->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}

