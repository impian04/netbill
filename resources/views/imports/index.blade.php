@extends('layouts.app')

@section('title', 'Import Data')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Import Data Pelanggan &amp; Tagihan</h1>
        <p class="page-subtitle">Unggah file spreadsheet (XLSX/CSV) untuk sinkronisasi tagihan bulanan</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('imports.template.xlsx') }}" id="btn-unduh-template-xlsx"
           class="btn btn-sm btn-success d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-excel"></i>Template Excel
        </a>
        <a href="{{ route('imports.template.csv') }}" id="btn-unduh-template-csv"
           class="btn btn-sm btn-secondary d-flex align-items-center gap-2">
            <i class="bi bi-filetype-csv"></i>Template CSV
        </a>
    </div>
</div>

{{-- ── Kartu Unggah File ───────────────────────────────────────── --}}
<!-- <div class="card mb-4" style="max-width: 680px;"> -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-upload me-2 text-primary"></i>Unggah File Spreadsheet
    </div>
    <div class="card-body">
        <div class="alert alert-info d-flex gap-2 py-2 mb-3" style="font-size: .83rem;">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
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
                <i class="bi bi-exclamation-triangle-fill"></i>
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
                <div class="form-text" style="font-size: 0.72rem;">Unduh template di atas untuk memastikan kesesuaian header kolom.</div>
            </div>
            <button type="submit" id="btn-upload" class="btn btn-sm btn-primary px-3">
                <i class="bi bi-eye me-1"></i>Pratinjau &amp; Lanjutkan
            </button>
        </form>
    </div>
</div>

{{-- ── Riwayat Batch Import ────────────────────────────────────── --}}
@if ($batches->isNotEmpty())
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Batch Import Terakhir</span>
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
                                <i class="bi bi-file-earmark-spreadsheet me-1 text-primary"></i>
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
                            <td class="text-center"><span class="badge bg-primary rounded-pill">{{ $batch->updated_count }}</span></td>
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
                                <a href="{{ route('imports.show', $batch) }}" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Lihat Status">
                                    <i class="bi bi-eye"></i>
                                </a>
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
        new window.DataTable('#import-history-table', {
            pageLength: 10,
            searching: false,
            language: {
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                infoEmpty: 'Menampilkan 0 data',
                zeroRecords: 'Tidak ada data import',
                paginate: {
                    first: 'Awal',
                    last: 'Akhir',
                    next: 'Berikutnya',
                    previous: 'Sebelumnya',
                }
            }
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
