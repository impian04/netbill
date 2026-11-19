<?php

namespace App\Http\Controllers;

use App\Exports\ImportTemplateExport;
use App\Imports\RowValidator;
use App\Jobs\ProcessImport;
use App\Models\ImportBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
        $user = auth()->user();
        $query = ImportBatch::latest();

        if (! $user->isSuperAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->pop_id) {
                    $q->orWhere('pop_id', $user->pop_id);
                }
            });
        }

        $batches = $query->limit(15)->get();

        return view('imports.index', compact('batches'));
    }

    /** Download Excel (.xlsx) template (primary) */
    public function templateXlsx(): StreamedResponse
    {
        $spreadsheet = ImportTemplateExport::build();

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'template-import-netbill.xlsx', [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-import-netbill.xlsx"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /** Download CSV template (secondary) */
    public function templateCsv(): StreamedResponse
    {
        $headers = ImportTemplateExport::HEADERS;
        $example = [
            'JCN-KTU-0001', 'Budi Santoso', '08123456789',
            'Jl. Merdeka No. 10, Kel. Sukamaju',
            '2023-01-15', 'Bulanan', '10 Mbps', 'ACTIVE', '2026-10', '220000', 'UNPAID', '2026-10-20',
            '0.5071000', '101.4478000',
        ];
        $example2 = [
            'JCN-KTU-0002', 'Siti Rahayu', '08987654321',
            'Jl. Pahlawan No. 5, RT 02/03',
            '2022-06-01', 'Bulanan', '20 Mbps', 'ACTIVE', '2026-10', '330000', 'PAID', '',
            '0.5123000', '101.4512000',
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

    /** Default template method (Excel .xlsx) */
    public function template(): StreamedResponse
    {
        return $this->templateXlsx();
    }

    /** Handle file upload → store temporary session → redirect to preview (PRG) */
    public function upload(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:' . (self::MAX_MB * 1024)],
        ], [
            'file.required' => 'File wajib diunggah.',
            'file.max'      => 'Ukuran file maksimal ' . self::MAX_MB . 'MB.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('imports.index')
                ->withErrors($validator)
                ->with('error', $validator->errors()->first());
        }

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, self::ALLOWED_EXTS, true)) {
            return redirect()->route('imports.index')
                ->withErrors(['file' => 'Format tidak didukung. Gunakan .xlsx atau .csv.'])
                ->with('error', 'Format tidak didukung. Gunakan .xlsx atau .csv.');
        }

        // Validate real MIME (not just extension)
        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            return redirect()->route('imports.index')
                ->withErrors(['file' => "Tipe file tidak valid (MIME: {$mime})."])
                ->with('error', "Tipe file tidak valid (MIME: {$mime}).");
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
            return redirect()->route('imports.index')
                ->withErrors(['file' => 'File tidak dapat dibaca: ' . $e->getMessage()])
                ->with('error', 'File tidak dapat dibaca: ' . $e->getMessage());
        }

        // Validate required headers exist
        $normalizedHeaders = array_map([RowValidator::class, 'normalizeHeader'], $preview['headers']);
        $required          = array_keys(RowValidator::REQUIRED_HEADERS);
        $missing           = array_diff($required, $normalizedHeaders);

        if ($missing) {
            Storage::delete($path);
            $labels = array_map(fn ($k) => RowValidator::REQUIRED_HEADERS[$k], $missing);
            return redirect()->route('imports.index')
                ->withErrors(['file' => 'Kolom tidak lengkap: ' . implode(', ', $labels)])
                ->with('error', 'Kolom tidak lengkap: ' . implode(', ', $labels));
        }

        $token = str()->random(40);
        session()->put("import_preview_{$token}", [
            'user_id'       => auth()->id(),
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'preview'       => $preview,
            'ext'           => $ext,
        ]);

        return redirect()->route('imports.preview', ['token' => $token]);
    }

    /** Display preview using session data */
    public function preview(Request $request, string $token): View|RedirectResponse
    {
        $sessionKey = "import_preview_{$token}";
        $data       = session()->get($sessionKey);

        if (! $data || ($data['user_id'] ?? null) !== auth()->id() || ! Storage::disk('local')->exists($data['file_path'] ?? '')) {
            return redirect()->route('imports.index')
                ->withErrors(['file' => 'Data pratinjau tidak ditemukan atau sudah kedaluwarsa. Silakan unggah ulang file.'])
                ->with('error', 'Data pratinjau tidak ditemukan atau sudah kedaluwarsa.');
        }

        return view('imports.preview', [
            'token'        => $token,
            'filePath'     => $data['file_path'],
            'originalName' => $data['original_name'],
            'preview'      => $data['preview'],
            'ext'          => $data['ext'],
        ]);
    }

    /** Confirm → dispatch job */
    public function confirm(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'duplicate_policy' => ['required', Rule::in(['skip', 'update'])],
        ], [
            'duplicate_policy.required' => 'Pilih kebijakan penanganan duplikat.',
            'duplicate_policy.in'       => 'Pilihan kebijakan duplikat tidak valid.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('imports.index')
                ->withErrors($validator)
                ->with('error', $validator->errors()->first());
        }

        $token      = (string) $request->input('token');
        $sessionKey = "import_preview_{$token}";
        $data       = $token ? session()->get($sessionKey) : null;

        if (! $data || ($data['user_id'] ?? null) !== auth()->id() || ! Storage::disk('local')->exists($data['file_path'] ?? '')) {
            return redirect()->route('imports.index')
                ->withErrors(['file' => 'Data pratinjau tidak ditemukan atau sudah kedaluwarsa. Silakan unggah ulang file.'])
                ->with('error', 'Data pratinjau tidak ditemukan atau sudah kedaluwarsa.');
        }

        $batch = ImportBatch::create([
            'user_id'          => auth()->id(),
            'pop_id'           => auth()->user()?->pop_id,
            'file_path'        => $data['file_path'],
            'original_name'    => $data['original_name'],
            'duplicate_policy' => $request->duplicate_policy,
            'status'           => 'queued',
        ]);

        session()->forget($sessionKey);

        \App\Services\ActivityLogger::log('import.confirm', "Menjadwalkan import file '{$batch->original_name}'", $batch);

        ProcessImport::dispatch($batch);

        return redirect()->route('imports.show', $batch)
            ->with('success', 'Import dijadwalkan. Halaman akan diperbarui otomatis.');
    }

    /** Show batch status / results */
    public function show(Request $request, ImportBatch $importBatch): View|\Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin()) {
            $isOwner   = (int) $importBatch->user_id === (int) $user->id;
            $isSamePop = $importBatch->pop_id && (int) $importBatch->pop_id === (int) $user->pop_id;

            if (! $isOwner && ! $isSamePop) {
                abort(404);
            }
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json($importBatch);
        }

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
                    $value = $cell->getValue();
                    if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                        $value = $value->getPlainText();
                    }
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
