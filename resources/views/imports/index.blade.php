@extends('layouts.app')

@section('title', 'Import Data')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Import Data Pelanggan &amp; Tagihan" subtitle="Unggah file spreadsheet (XLSX/CSV) untuk sinkronisasi tagihan bulanan">
    <x-slot:actions>
        <x-btn variant="approve" href="{{ route('imports.template.xlsx') }}" id="btn-unduh-template-xlsx"
            >
            Template Excel
        </x-btn>
        <x-btn variant="neutral" href="{{ route('imports.template.csv') }}" id="btn-unduh-template-csv">
            Template CSV
        </x-btn>
    </x-slot:actions>
</x-page-header>

{{-- ── Kartu Unggah File ───────────────────────────────────────── --}}
<!-- <div class="card mb-4" style="max-width: 680px;"> -->
<div class="card mb-4">
    <div class="card-header">
        Unggah File Spreadsheet
    </div>
    <div class="card-body">
        <div class="alert alert-info d-flex gap-2 py-2 mb-3">
            <div>
                <strong>Disarankan menggunakan template Excel (.xlsx)</strong> agar format teks seperti Nomor Telepon dan ID Pelanggan tidak terpotong atau terformat menjadi notasi ilmiah. File .csv tetap didukung.
                <br>Format: <strong>.xlsx</strong> (utama) atau <strong>.csv</strong> &middot; Maksimal: <strong>5 MB</strong>
                <br>Kolom wajib: ID Pelanggan, Nama, Alamat, Tanggal Mulai, Jenis Tagihan, Paket, Periode, Nominal, Status. 
                <br>Kolom opsional: Nomor Telepon, Status Layanan, Jatuh Tempo (YYYY-MM-DD atau DD/MM/YYYY).
                <br>Periode: format <code>2026-10</code> atau <code>Oktober 2026</code> &middot; Status: <code>PAID</code> / <code>UNPAID</code>.
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger d-flex gap-2 py-2 mb-3">
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('imports.upload') }}"
              enctype="multipart/form-data" id="form-upload" novalidate>
            @csrf
            <div class="mb-3">
                <label for="file-input" class="form-label fw-semibold small">
                    Pilih File <span class="text-danger">*</span>
                </label>
                <input type="file" id="file-input" name="file"
                       class="form-control form-control-sm @error('file') is-invalid @enderror"
                       accept=".xlsx,.csv" required>
                @error('file')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Unduh template di atas untuk memastikan kesesuaian header kolom.</div>
            </div>
            <x-btn type="submit" variant="primary"   id="btn-upload"  >
                Pratinjau &amp; Lanjutkan
            </x-btn>
        </form>
    </div>
</div>

{{-- ── Riwayat Batch Import ────────────────────────────────────── --}}
@if ($batches->isNotEmpty())
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Riwayat Batch Import Terakhir</span>
        <span class="text-muted small">{{ $batches->count() }} batch terbaru</span>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 w-100" id="import-history-table">
                <thead>
                    <tr>
                        <th>Nama File</th>
                        <th>Kebijakan Duplikat</th>
                        <th>Status</th>
                        <th class="text-center">Dibuat</th>
                        <th class="text-center">Diperbarui</th>
                        <th class="text-center">Dilewati</th>
                        <th class="text-center">Error</th>
                        <th>Waktu Import</th>
                        <th class="text-end" style="width: 60px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($batches as $batch)
                        <tr>
                            <td class="small fw-semibold">
                                {{ $batch->original_name }}
                            </td>
                            <td>
                                @if ($batch->duplicate_policy === 'skip')
                                    <span class="badge bg-light text-dark border">Lewati</span>
                                @else
                                    <span class="badge bg-light text-dark border">Perbarui</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $batch->statusColor() }}">
                                    {{ $batch->statusLabel() }}
                                </span>
                            </td>
                            <td class="text-center"><span class="badge bg-success rounded-pill">{{ $batch->created_count }}</span></td>
                            <td class="text-center"><span class="badge bg-info rounded-pill">{{ $batch->updated_count }}</span></td>
                            <td class="text-center"><span class="badge bg-secondary rounded-pill">{{ $batch->skipped_count }}</span></td>
                            <td class="text-center">
                                @if ($batch->error_count > 0)
                                    <span class="badge badge-unpaid rounded-pill">{{ $batch->error_count }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ $batch->created_at->diffForHumans() }}
                            </td>
                            <td class="text-end">
                                <x-btn variant="neutral" href="{{ route('imports.show', $batch) }}"   title="Lihat Status">
                                    </x-btn>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Inisialisasi DataTable client-side untuk riwayat batch import
    if (document.getElementById('import-history-table')) {
        window.initClientTable('#import-history-table', {
            pageLength: 10,
            searching: false,
        });
    }

    // Spinner saat submit unggah file
    document.getElementById('form-upload')?.addEventListener('submit', function () {
        var btn = document.getElementById('btn-upload');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memuat pratinjau...';
        btn.disabled = true;
    });
});
</script>
@endpush
