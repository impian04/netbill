<?php

namespace App\Http\Controllers;

use App\Models\Pop;
use App\Services\MasterImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterImportController extends Controller
{
    private const ALLOWED_TYPES = ['odc', 'odp', 'material'];
    private const MAX_MB = 5;

    private function checkAccess(): void
    {
        $user = auth()->user();
        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdmin())) {
            abort(403, 'Akses ditolak. Hanya Admin dan Super Admin yang dapat melakukan import master data.');
        }
    }

    private function validateType(string $type): void
    {
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            abort(404, "Jenis master data '{$type}' tidak didukung.");
        }
    }

    /**
     * Download template XLSX atau CSV
     */
    public function template(Request $request, string $type): StreamedResponse
    {
        $this->checkAccess();
        $this->validateType($type);

        $format = strtolower($request->query('format', 'xlsx'));
        if ($format === 'csv') {
            return MasterImportService::downloadTemplateCsv($type);
        }

        return MasterImportService::downloadTemplateXlsx($type);
    }

    /**
     * Upload berkas spreadsheet dan redirect ke preview (PRG)
     */
    public function upload(Request $request, string $type): RedirectResponse
    {
        $this->checkAccess();
        $this->validateType($type);
        $user = auth()->user();

        $redirectRoute = match ($type) {
            'odc'      => 'odcs.index',
            'odp'      => 'odps.index',
            'material' => 'materials.index',
        };

        $rules = [
            'file' => ['required', 'file', 'max:' . (self::MAX_MB * 1024)],
        ];

        if ($user->isSuperAdmin()) {
            $rules['pop_id'] = ['required', 'exists:pops,id'];
        }

        $validator = Validator::make($request->all(), $rules, [
            'file.required'   => 'Berkas spreadsheet wajib diunggah.',
            'file.max'        => 'Ukuran berkas maksimal ' . self::MAX_MB . ' MB.',
            'pop_id.required' => 'POP target wajib dipilih.',
            'pop_id.exists'   => 'POP target tidak valid.',
        ]);

        if ($validator->fails()) {
            return redirect()->route($redirectRoute)
                ->withErrors($validator)
                ->with('error', $validator->errors()->first());
        }

        $popId = $user->isSuperAdmin() ? (int) $request->input('pop_id') : (int) $user->pop_id;
        $pop = Pop::find($popId);
        if (! $pop) {
            return redirect()->route($redirectRoute)->with('error', 'POP tidak ditemukan.');
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv'], true)) {
            return redirect()->route($redirectRoute)
                ->withErrors(['file' => 'Format berkas tidak didukung. Harap gunakan format .xlsx atau .csv.'])
                ->with('error', 'Format berkas tidak didukung. Harap gunakan format .xlsx atau .csv.');
        }

        // Simpan sementara di storage
        $storedPath = $file->storeAs(
            'imports/master',
            $type . '_' . now()->format('YmdHis') . '_' . str()->random(8) . '.' . $ext,
            'local'
        );

        try {
            $parsed = MasterImportService::readRows(Storage::disk('local')->path($storedPath), $ext);
        } catch (ValidationException $ve) {
            Storage::disk('local')->delete($storedPath);
            return redirect()->route($redirectRoute)
                ->withErrors($ve->errors())
                ->with('error', $ve->getMessage());
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($storedPath);
            return redirect()->route($redirectRoute)
                ->withErrors(['file' => 'Gagal membaca berkas: ' . $e->getMessage()])
                ->with('error', 'Gagal membaca berkas: ' . $e->getMessage());
        }

        if ($parsed['total_rows'] === 0) {
            Storage::disk('local')->delete($storedPath);
            return redirect()->route($redirectRoute)
                ->withErrors(['file' => 'Berkas tidak memuat data yang dapat diimpor.'])
                ->with('error', 'Berkas kosong.');
        }

        $token = str()->random(40);
        session()->put("master_import_preview_{$token}", [
            'type'          => $type,
            'pop_id'        => $pop->id,
            'pop_code'      => $pop->code,
            'pop_name'      => $pop->name,
            'user_id'       => $user->id,
            'file_path'     => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'headers'       => $parsed['headers'],
            'rows'          => $parsed['rows'],
            'preview'       => $parsed['preview'],
            'total_rows'    => $parsed['total_rows'],
        ]);

        return redirect()->route('master-import.preview', [
            'type'  => $type,
            'token' => $token,
        ]);
    }

    /**
     * Tampilan pratinjau ringkas sebelum konfirmasi import (PRG)
     */
    public function preview(Request $request, string $type): View|RedirectResponse
    {
        $this->checkAccess();
        $this->validateType($type);

        $token = (string) $request->query('token');
        $sessionKey = "master_import_preview_{$token}";
        $data = session()->get($sessionKey);

        $backRoute = match ($type) {
            'odc'      => 'odcs.index',
            'odp'      => 'odps.index',
            'material' => 'materials.index',
        };

        if (! $data || ($data['user_id'] ?? null) !== auth()->id() || ($data['type'] ?? null) !== $type) {
            return redirect()->route($backRoute)
                ->withErrors(['file' => 'Sesi pratinjau import telah kedaluwarsa. Silakan unggah kembali berkas Anda.'])
                ->with('error', 'Sesi pratinjau import telah kedaluwarsa.');
        }

        return view('master_import.preview', [
            'type'         => $type,
            'token'        => $token,
            'data'         => $data,
            'backRoute'    => route($backRoute),
            'confirmRoute' => route('master-import.confirm', $type),
        ]);
    }

    /**
     * Konfirmasi eksekusi import (PRG)
     */
    public function confirm(Request $request, string $type): RedirectResponse
    {
        $this->checkAccess();
        $this->validateType($type);
        $user = auth()->user();

        $token = (string) $request->input('token');
        $sessionKey = "master_import_preview_{$token}";
        $data = session()->get($sessionKey);

        $backRoute = match ($type) {
            'odc'      => 'odcs.index',
            'odp'      => 'odps.index',
            'material' => 'materials.index',
        };

        if (! $data || ($data['user_id'] ?? null) !== $user->id || ($data['type'] ?? null) !== $type) {
            return redirect()->route($backRoute)
                ->withErrors(['file' => 'Sesi import telah kedaluwarsa. Silakan ulangi proses import.'])
                ->with('error', 'Sesi import telah kedaluwarsa.');
        }

        try {
            $result = MasterImportService::executeImport(
                $type,
                (int) $data['pop_id'],
                $data['headers'],
                $data['rows'],
                $user
            );
        } catch (\Throwable $e) {
            return redirect()->route($backRoute)
                ->with('error', 'Terjadi kesalahan saat memproses import: ' . $e->getMessage());
        } finally {
            if (isset($data['file_path'])) {
                Storage::disk('local')->delete($data['file_path']);
            }
            session()->forget($sessionKey);
        }

        $typeLabel = match ($type) {
            'odc'      => 'ODC',
            'odp'      => 'ODP',
            'material' => 'Barang',
        };

        $msg = "Import {$typeLabel} selesai: {$result['imported']} data berhasil ditambahkan, {$result['skipped']} dilewati (duplikat), {$result['errors']} baris error.";

        return redirect()->route($backRoute)
            ->with('success', $msg)
            ->with('import_result_details', $result['reportDetails'] ?? []);
    }
}

