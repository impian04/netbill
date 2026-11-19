<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterImportService
{
    public const MAX_ROWS = 2000;

    public const TEMPLATES = [
        'odc' => [
            'title'   => 'Template Import ODC',
            'headers' => ['name', 'latitude', 'longitude'],
            'sample'  => [
                ['ODC-01', '-6.2000000', '106.8166667'],
                ['ODC-02', '-6.2100000', '106.8200000'],
            ],
        ],
        'odp' => [
            'title'   => 'Template Import ODP',
            'headers' => ['odc_name', 'name', 'latitude', 'longitude'],
            'sample'  => [
                ['ODC-01', 'ODP-01-01', '-6.2001000', '106.8167000'],
                ['ODC-01', 'ODP-01-02', '-6.2002000', '106.8168000'],
            ],
        ],
        'material' => [
            'title'   => 'Template Import Barang',
            'headers' => ['name', 'unit'],
            'sample'  => [
                ['Kabel Dropcore 1 Core', 'meter'],
                ['ONT ZTE F609', 'unit'],
                ['Patchcord SC-UPC 3M', 'pcs'],
                ['Konektor Fast Connector', 'pcs'],
                ['Kabel Fiber Optic 24 Core', 'roll'],
                ['Toolkit Fiber Optic', 'set'],
            ],
        ],
    ];

    /**
     * Download Excel template (.xlsx)
     */
    public static function downloadTemplateXlsx(string $type): StreamedResponse
    {
        $config = self::TEMPLATES[$type] ?? abort(404);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($config['title'], 0, 31));

        // Header
        $headers = $config['headers'];
        foreach ($headers as $colIdx => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE2E8F0');

        // Sample rows
        $rowNum = 2;
        foreach ($config['sample'] as $row) {
            foreach ($row as $colIdx => $val) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                $sheet->setCellValueExplicit("{$colLetter}{$rowNum}", (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $rowNum++;
        }

        $fileName = "template-import-{$type}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Download CSV template (.csv)
     */
    public static function downloadTemplateCsv(string $type): StreamedResponse
    {
        $config = self::TEMPLATES[$type] ?? abort(404);
        $fileName = "template-import-{$type}.csv";

        return response()->streamDownload(function () use ($config) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($out, $config['headers']);
            foreach ($config['sample'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $fileName, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Baca berkas spreadsheet dan ambil ringkasan serta baris pratinjau
     */
    public static function readRows(string $fullPath, string $ext): array
    {
        $headers = null;
        $rows = [];

        if ($ext === 'xlsx') {
            $spreadsheet = IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet();

            foreach ($sheet->getRowIterator() as $row) {
                $iter = $row->getCellIterator();
                $iter->setIterateOnlyExistingCells(false);
                $rowData = [];
                foreach ($iter as $cell) {
                    $val = $cell->getValue();
                    if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                        $val = $val->getPlainText();
                    }
                    $rowData[] = $val !== null ? trim((string) $val) : '';
                }

                if (! array_filter($rowData, fn ($v) => $v !== '')) {
                    continue; // skip baris kosong
                }

                if ($headers === null) {
                    $headers = array_map('strtolower', $rowData);
                    continue;
                }

                $rows[] = $rowData;
            }
        } else {
            if (($handle = fopen($fullPath, 'r')) !== false) {
                while (($data = fgetcsv($handle)) !== false) {
                    $cleaned = array_map(fn ($v) => trim((string) $v), $data);
                    if (! array_filter($cleaned, fn ($v) => $v !== '')) {
                        continue;
                    }

                    if ($headers === null) {
                        $headers = array_map('strtolower', $cleaned);
                        continue;
                    }

                    $rows[] = $cleaned;
                }
                fclose($handle);
            }
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => "Jumlah baris data (" . count($rows) . ") melebihi batas maksimal " . self::MAX_ROWS . " baris.",
            ]);
        }

        return [
            'headers'    => $headers ?? [],
            'rows'       => $rows,
            'total_rows' => count($rows),
            'preview'    => array_slice($rows, 0, 5),
        ];
    }

    /**
     * Eksekusi import secara sinkron dalam satu transaksi DB
     */
    public static function executeImport(string $type, int $popId, array $headers, array $rows, User $user): array
    {
        $config = self::TEMPLATES[$type] ?? throw new \InvalidArgumentException("Tipe import {$type} tidak valid.");
        $requiredHeaders = $config['headers'];

        // Cek kelengkapan kolom header
        foreach ($requiredHeaders as $req) {
            if (! in_array($req, $headers, true)) {
                throw ValidationException::withMessages([
                    'file' => "Kolom wajib '{$req}' tidak ditemukan pada file. Header yang ada: " . implode(', ', $headers),
                ]);
            }
        }

        $headerMap = array_flip($headers);

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $reportDetails = [];

        $pop = Pop::findOrFail($popId);

        DB::transaction(function () use ($type, $pop, $rows, $headerMap, &$imported, &$skipped, &$errors, &$reportDetails) {
            foreach ($rows as $idx => $row) {
                $lineNum = $idx + 2; // header baris 1
                try {
                    if ($type === 'odc') {
                        $name = trim((string) ($row[$headerMap['name']] ?? ''));
                        $latRaw = trim((string) ($row[$headerMap['latitude']] ?? ''));
                        $lngRaw = trim((string) ($row[$headerMap['longitude']] ?? ''));

                        if ($name === '') {
                            $errors++;
                            $reportDetails[] = "Baris {$lineNum}: Nama ODC wajib diisi.";
                            continue;
                        }

                        if (strlen($name) > 100) {
                            $errors++;
                            $reportDetails[] = "Baris {$lineNum}: Nama ODC melebihi 100 karakter.";
                            continue;
                        }

                        // Cek duplikat dalam POP
                        if (Odc::where('pop_id', $pop->id)->where('name', $name)->exists()) {
                            $skipped++;
                            $reportDetails[] = "Baris {$lineNum}: ODC '{$name}' sudah ada di POP {$pop->code} (dilewati).";
                            continue;
                        }

                        $lat = self::parseCoordinate($latRaw, -11.5, 6.5);
                        $lng = self::parseCoordinate($lngRaw, 94.0, 141.5);

                        Odc::create([
                            'pop_id'    => $pop->id,
                            'name'      => $name,
                            'latitude'  => $lat,
                            'longitude' => $lng,
                            'is_active' => true,
                        ]);

                        $imported++;
                    } elseif ($type === 'odp') {
                        $odcName = trim((string) ($row[$headerMap['odc_name']] ?? ''));
                        $name = trim((string) ($row[$headerMap['name']] ?? ''));
                        $latRaw = trim((string) ($row[$headerMap['latitude']] ?? ''));
                        $lngRaw = trim((string) ($row[$headerMap['longitude']] ?? ''));

                        if ($name === '') {
                            $errors++;
                            $reportDetails[] = "Baris {$lineNum}: Nama ODP wajib diisi.";
                            continue;
                        }

                        if (strlen($name) > 100) {
                            $errors++;
                            $reportDetails[] = "Baris {$lineNum}: Nama ODP melebihi 100 karakter.";
                            continue;
                        }

                        if ($odcName === '') {
                            $errors++;
                            $reportDetails[] = "Baris {$lineNum}: Kolom odc_name wajib diisi untuk ODP '{$name}'.";
                            continue;
                        }

                        $odc = Odc::where('pop_id', $pop->id)->where('name', $odcName)->first();
                        if (! $odc) {
                            $errors++;
                            $reportDetails[] = "Baris {$lineNum}: ODC '{$odcName}' tidak ditemukan pada POP {$pop->code}.";
                            continue;
                        }

                        // Cek duplikat dalam POP
                        if (Odp::where('pop_id', $pop->id)->where('name', $name)->exists()) {
                            $skipped++;
                            $reportDetails[] = "Baris {$lineNum}: ODP '{$name}' sudah ada di POP {$pop->code} (dilewati).";
                            continue;
                        }

                        $lat = self::parseCoordinate($latRaw, -11.5, 6.5);
                        $lng = self::parseCoordinate($lngRaw, 94.0, 141.5);

                        Odp::create([
                            'pop_id'    => $pop->id,
                            'odc_id'    => $odc->id,
                            'name'      => $name,
                            'latitude'  => $lat,
                            'longitude' => $lng,
                            'is_active' => true,
                        ]);

                        $imported++;
                    } elseif ($type === 'material') {
                        $name = trim((string) ($row[$headerMap['name']] ?? ''));
                        $unit = strtolower(trim((string) ($row[$headerMap['unit']] ?? '')));

                        if ($name === '') {
                            $errors++;
                            $reportDetails[] = "Baris {$lineNum}: Nama barang wajib diisi.";
                            continue;
                        }

                        if (strlen($name) > 100) {
                            $errors++;
                            $reportDetails[] = "Baris {$lineNum}: Nama barang melebihi 100 karakter.";
                            continue;
                        }

                        if (! in_array($unit, Material::UNITS, true)) {
                            $errors++;
                            $validUnits = implode(', ', Material::UNITS);
                            $reportDetails[] = "Baris {$lineNum}: Satuan '{$unit}' tidak valid. Pilihan: {$validUnits}.";
                            continue;
                        }

                        // Cek duplikat dalam POP
                        if (Material::where('pop_id', $pop->id)->where('name', $name)->exists()) {
                            $skipped++;
                            $reportDetails[] = "Baris {$lineNum}: Barang '{$name}' sudah ada di POP {$pop->code} (dilewati).";
                            continue;
                        }

                        Material::create([
                            'pop_id'    => $pop->id,
                            'name'      => $name,
                            'unit'      => $unit,
                            'is_active' => true,
                        ]);

                        $imported++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $reportDetails[] = "Baris {$lineNum}: Terjadi kesalahan - " . $e->getMessage();
                }
            }
        });

        ActivityLogger::log(
            "master.import.{$type}",
            "Import {$type}: {$imported} berhasil, {$skipped} dilewati, {$errors} error di POP {$pop->code}",
            null,
            $pop->id
        );

        return [
            'type'          => $type,
            'pop_code'      => $pop->code,
            'pop_name'      => $pop->name,
            'imported'      => $imported,
            'skipped'       => $skipped,
            'errors'        => $errors,
            'total_rows'    => count($rows),
            'reportDetails' => $reportDetails,
        ];
    }

    private static function parseCoordinate(?string $val, float $min, float $max): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }

        if (! is_numeric($val)) {
            return null;
        }

        $num = (float) $val;
        if ($num < $min || $num > $max) {
            return null;
        }

        return $num;
    }
}
