<?php

namespace App\Http\Controllers;

use App\Imports\RowValidator;
use App\Jobs\ProcessImport;
use App\Models\ImportBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    private const MAX_MB        = 5;
    private const ALLOWED_EXTS  = ['xlsx', 'csv'];
    private const ALLOWED_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'text/plain',
        'application/csv',
        'application/octet-stream',
    ];

    public function index(): View
    {
        $batches = ImportBatch::latest()->limit(15)->get();

        return view('imports.index', compact('batches'));
    }

    /** Download CSV template */
    public function template(): StreamedResponse
    {
        $headers = [
            'ID Pelanggan', 'Nama', 'Nomor Telepon', 'Alamat',
            'Tanggal Mulai Berlangganan', 'Jenis Tagihan', 'Paket', 'Status Layanan',
            'Periode', 'Nominal Ditangguhkan', 'Status',
        ];
        $example = [
            'SLX-GS-00001', 'Budi Santoso', '628123456789',
            'Jl. Merdeka No. 10, Kel. Sukamaju',
            '2023-01-15', 'Bulanan', '10 Mbps', 'ACTIVE', '2026-10', '220000', 'UNPAID',
        ];
        $example2 = [
            'SLX-GS-00002', 'Siti Rahayu', '628987654321',
            'Jl. Pahlawan No. 5, RT 02/03',
            '2022-06-01', 'Bulanan', '20 Mbps', 'ACTIVE', '2026-10', '330000', 'PAID',
        ];

        return response()->streamDownload(function () use ($headers, $example, $example2) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, $example);
            fputcsv($out, $example2);
            fclose($out);
        }, 'template-import-netbill.csv', [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template-import-netbill.csv"',
        ]);
    }

    /** Handle file upload → show preview */
    public function upload(Request $request): RedirectResponse|View
    {
        $request->validate(
            ['file' => ['required', 'file', 'max:' . (self::MAX_MB * 1024)]],
            ['file.required' => 'File wajib diunggah.', 'file.max' => 'Ukuran file maksimal ' . self::MAX_MB . 'MB.']
        );

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, self::ALLOWED_EXTS, true)) {
            return back()->withErrors(['file' => 'Format tidak didukung. Gunakan .xlsx atau .csv.']);
        }

        // Validate real MIME (not just extension)
        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            return back()->withErrors(['file' => "Tipe file tidak valid (MIME: {$mime})."]);;
        }

        // Store in private disk
        $path = $file->storeAs(
            'imports',
            now()->format('YmdHis') . '_' . str()->random(8) . '.' . $ext,
            'local'
        );

        // Read headers + preview rows
        try {
            $preview = $this->readPreview(Storage::path($path), $ext);
        } catch (\Throwable $e) {
            Storage::delete($path);
            return back()->withErrors(['file' => 'File tidak dapat dibaca: ' . $e->getMessage()]);
        }

        // Validate required headers exist
        $normalizedHeaders = array_map([RowValidator::class, 'normalizeHeader'], $preview['headers']);
        $required          = array_keys(RowValidator::REQUIRED_HEADERS);
        $missing           = array_diff($required, $normalizedHeaders);

        if ($missing) {
            Storage::delete($path);
            $labels = array_map(fn ($k) => RowValidator::REQUIRED_HEADERS[$k], $missing);
            return back()->withErrors(['file' => 'Kolom tidak lengkap: ' . implode(', ', $labels)]);
        }

        return view('imports.preview', [
            'filePath'     => $path,
            'originalName' => $file->getClientOriginalName(),
            'preview'      => $preview,
            'ext'          => $ext,
        ]);
    }

    /** Confirm → dispatch job */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'file_path'        => ['required', 'string', 'max:500'],
            'original_name'    => ['required', 'string', 'max:255'],
            'duplicate_policy' => ['required', Rule::in(['skip', 'update'])],
        ]);

        if (! Storage::exists($request->file_path)) {
            return redirect()->route('imports.index')
                ->withErrors(['file' => 'File tidak ditemukan. Silakan unggah ulang.']);
        }

        $batch = ImportBatch::create([
            'file_path'        => $request->file_path,
            'original_name'    => $request->original_name,
            'duplicate_policy' => $request->duplicate_policy,
            'status'           => 'queued',
        ]);

        ProcessImport::dispatch($batch);

        return redirect()->route('imports.show', $batch)
            ->with('success', 'Import dijadwalkan. Halaman akan diperbarui otomatis.');
    }

    /** Show batch status / results */
    public function show(ImportBatch $importBatch): View
    {
        return view('imports.show', ['batch' => $importBatch]);
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function readPreview(string $fullPath, string $ext): array
    {
        $rows    = [];
        $headers = null;
        $total   = 0;

        if ($ext === 'xlsx') {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $sheet       = $spreadsheet->getActiveSheet();

            foreach ($sheet->getRowIterator() as $row) {
                $iter = $row->getCellIterator();
                $iter->setIterateOnlyExistingCells(false);
                $rowData = [];
                foreach ($iter as $cell) {
                    $rowData[] = $cell->getFormattedValue();
                }

                if (! array_filter($rowData, fn ($v) => trim((string) $v) !== '')) {
                    continue; // skip blank rows
                }

                if ($headers === null) {
                    $headers = $rowData;
                    continue;
                }

                $total++;
                if (count($rows) < 10) {
                    $rows[] = $rowData;
                }
            }
        } else {
            if (($handle = fopen($fullPath, 'r')) !== false) {
                while (($data = fgetcsv($handle)) !== false) {
                    if (! array_filter($data, fn ($v) => trim((string) $v) !== '')) {
                        continue;
                    }
                    if ($headers === null) {
                        $headers = $data;
                        continue;
                    }
                    $total++;
                    if (count($rows) < 10) {
                        $rows[] = $data;
                    }
                }
                fclose($handle);
            }
        }

        return [
            'headers'    => $headers ?? [],
            'rows'       => $rows,
            'total_rows' => $total,
        ];
    }
}
