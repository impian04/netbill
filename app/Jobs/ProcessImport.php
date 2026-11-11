<?php

namespace App\Jobs;

use App\Imports\RowValidator;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToArray;

class ProcessImport implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    private const CHUNK_SIZE = 100;

    public function __construct(public ImportBatch $batch) {}

    public function handle(): void
    {
        $this->batch->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $fullPath = Storage::path($this->batch->file_path);

            if (! file_exists($fullPath)) {
                throw new \RuntimeException('File tidak ditemukan di storage.');
            }

            $this->processFile($fullPath);

            $this->batch->update(['status' => 'done', 'finished_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('ProcessImport failed', ['batch' => $this->batch->id, 'error' => $e->getMessage()]);

            $existingErrors = $this->batch->fresh()->errors ?? [];
            $existingErrors[] = ['row' => 0, 'messages' => ['Proses gagal: ' . $e->getMessage()]];

            $this->batch->update([
                'status'      => 'failed',
                'finished_at' => now(),
                'errors'      => $existingErrors,
            ]);
        }
    }

    private function processFile(string $fullPath): void
    {
        $ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $rows = $this->readAllRows($fullPath, $ext);

        // First row = headers
        if (empty($rows)) {
            throw new \RuntimeException('File kosong atau tidak dapat dibaca.');
        }

        $rawHeaders = array_shift($rows);
        $headers    = array_map([RowValidator::class, 'normalizeHeader'], $rawHeaders);

        $totalRows = count($rows);
        $this->batch->update(['total_rows' => $totalRows]);

        // Process in chunks
        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            $this->processChunk($headers, $chunk);
        }

        // Clean up file after successful processing
        Storage::delete($this->batch->file_path);
    }

    private function readAllRows(string $path, string $ext): array
    {
        // Use PhpSpreadsheet directly for reliable reading
        if ($ext === 'xlsx') {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows  = [];
            foreach ($sheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    // Resolve formula result
                    if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                        $value = $value->getPlainText();
                    }
                    // Date cells: get formatted date
                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell) && is_numeric($value)) {
                        try {
                            $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                        } catch (\Throwable) {
                            $value = $cell->getFormattedValue();
                        }
                    } elseif (is_float($value) || is_int($value)) {
                        if (floor($value) == $value) {
                            $value = number_format($value, 0, '', '');
                        }
                    }
                    $rowData[] = $value !== null ? (string) $value : '';
                }
                // Skip completely empty rows
                if (array_filter($rowData, fn ($v) => $v !== null && $v !== '')) {
                    $rows[] = $rowData;
                }
            }
            return $rows;
        }

        // CSV
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                if (array_filter($data, fn ($v) => trim((string) $v) !== '')) {
                    $rows[] = $data;
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    private function processChunk(array $headers, array $chunkRows): void
    {
        $chunkErrors  = [];
        $validRows    = [];
        $headerCount  = count($headers);

        foreach ($chunkRows as $rowNum => $rawRow) {
            // Pad short rows, trim long rows
            $rawRow  = array_slice(array_pad($rawRow, $headerCount, null), 0, $headerCount);
            $mapped  = array_combine($headers, $rawRow);

            $errors = RowValidator::validate($mapped);
            if ($errors) {
                // +2: 1 for header row, 1 for 0-index
                $chunkErrors[] = ['row' => $rowNum + 2, 'messages' => $errors];
            } else {
                $validRows[] = ['row_num' => $rowNum + 2, 'mapped' => $mapped];
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $txErrors = [];

        try {
            DB::transaction(function () use ($validRows, &$created, &$updated, &$skipped, &$txErrors) {
                foreach ($validRows as $item) {
                    $result = $this->upsertRow($item['mapped'], $item['row_num']);
                    if ($result === 'created') {
                        $created++;
                    } elseif ($result === 'updated') {
                        $updated++;
                    } elseif ($result === 'skipped') {
                        $skipped++;
                    } elseif (is_string($result)) {
                        // Error message returned
                        $txErrors[] = ['row' => $item['row_num'], 'messages' => [$result]];
                    }
                }

                // Pastikan seluruh nomor tagihan baru pada chunk ini memiliki format resmi INV-YYYYMM-XXXXXX
                Invoice::fixDraftNumbers();
            });
        } catch (\Throwable $e) {
            // Whole chunk transaction failed
            foreach ($validRows as $item) {
                $txErrors[] = ['row' => $item['row_num'], 'messages' => ['DB error: ' . $e->getMessage()]];
            }
            $created = 0;
            $updated = 0;
        }

        $allErrors = array_merge($chunkErrors, $txErrors);

        // Update batch counters atomically (outside transaction)
        $this->batch->increment('processed_rows', count($chunkRows));
        $this->batch->increment('created_count', $created);
        $this->batch->increment('updated_count', $updated);
        $this->batch->increment('skipped_count', $skipped);
        $this->batch->increment('error_count', count($allErrors));

        if ($allErrors) {
            $fresh = $this->batch->fresh();
            $fresh->update(['errors' => array_merge($fresh->errors ?? [], $allErrors)]);
        }
    }

    /** @return 'created'|'updated'|'skipped'|string(error) */
    private function upsertRow(array $row, int $rowNum): string
    {
        $code   = strtoupper(trim($row['id_pelanggan']));
        $period = RowValidator::parsePeriod($row['periode']);

        if (! $period) {
            return "Baris {$rowNum}: periode tidak dapat diparsing '{$row['periode']}'";
        }

        $subscribedAt = RowValidator::parseFlexibleDate($row['tanggal_mulai_berlangganan']);
        if (! $subscribedAt) {
            return "Baris {$rowNum}: tanggal tidak valid";
        }

        $amount = RowValidator::parseAmount($row['nominal_ditangguhkan']);
        $status = RowValidator::normalizeStatus($row['status']);

        // Check for existing invoice (duplicate detection)
        $customer        = Customer::where('customer_code', $code)->first();
        $existingInvoice = null;

        if ($customer) {
            $existingInvoice = Invoice::where('customer_id', $customer->id)
                ->where('period', $period)
                ->first();
        }

        // Apply duplicate policy
        if ($existingInvoice && $this->batch->duplicate_policy === 'skip') {
            return 'skipped';
        }

        // Normalize phone (optional)
        $phone = trim($row['nomor_telepon'] ?? '');
        $normalizedPhone = $phone !== '' ? \App\Http\Requests\StoreCustomerRequest::normalizePhone($phone) : null;

        $serviceStatus = RowValidator::normalizeServiceStatus($row['status_layanan'] ?? 'ACTIVE');

        // Upsert customer
        $customer = Customer::updateOrCreate(
            ['customer_code' => $code],
            [
                'name'           => trim($row['nama']),
                'phone'          => $normalizedPhone,
                'address'        => trim($row['alamat']),
                'subscribed_at'  => $subscribedAt,
                'billing_type'   => trim($row['jenis_tagihan']),
                'package'        => trim($row['paket']),
                'service_status' => $serviceStatus,
            ]
        );

        // Upsert invoice
        $dueDateRaw = trim((string) ($row['jatuh_tempo'] ?? $row['due_date'] ?? ''));
        $dueDate    = $dueDateRaw !== '' ? RowValidator::parseFlexibleDate($dueDateRaw) : null;

        $invoiceData = [
            'customer_id'     => $customer->id,
            'period'          => $period,
            'deferred_amount' => $amount,
            'status'          => $status,
        ];

        if ($dueDate !== null) {
            $invoiceData['due_date'] = $dueDate;
        }

        if ($existingInvoice) {
            $existingInvoice->update($invoiceData);
            return 'updated';
        }

        Invoice::create($invoiceData);
        return 'created';
    }
}
