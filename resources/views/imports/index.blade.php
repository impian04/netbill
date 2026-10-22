@extends('layouts.app')

@section('title', 'Import Data')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Import Data</h1>
        <p class="text-muted small mb-0">Unggah file xlsx/csv untuk import pelanggan &amp; tagihan</p>
    </div>
    <a href="{{ route('imports.template') }}" id="btn-unduh-template"
       class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2">
        <i class="bi bi-download"></i>Unduh Template CSV
    </a>
</div>

{{-- Upload card --}}
<div class="card mb-4" style="max-width:680px;">
    <div class="card-header">
        <i class="bi bi-upload me-2"></i>Unggah File
    </div>
    <div class="card-body">

        {{-- Info --}}
        <div class="alert alert-info d-flex gap-2 py-2 mb-3" style="font-size:.83rem;">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div>
                Format: <strong>.xlsx</strong> atau <strong>.csv</strong> &middot;
                Maks: <strong>5 MB</strong> &middot;
                Kolom wajib: ID Pelanggan, Nama, Alamat, Tanggal Mulai, Jenis Tagihan, Paket, Periode, Nominal, Status.
                <br>Periode: format <code>2026-10</code> atau <code>Oktober 2026</code>.
                Status: <code>PAID</code> / <code>UNPAID</code> (atau Lunas / Belum Bayar).
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
                <label for="file-input" class="form-label fw-semibold">
                    Pilih File <span class="text-danger">*</span>
                </label>
                <input type="file" id="file-input" name="file"
                       class="form-control @error('file') is-invalid @enderror"
                       accept=".xlsx,.csv">
                @error('file')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Unduh template di atas untuk melihat format yang benar.</div>
            </div>
            <button type="submit" id="btn-upload" class="btn btn-primary">
                <i class="bi bi-eye me-1"></i>Pratinjau &amp; Lanjutkan
            </button>
        </form>
    </div>
</div>

{{-- Import history --}}
@if ($batches->isNotEmpty())
<div class="card">
    <div class="card-header">
        <i class="bi bi-clock-history me-2"></i>Riwayat Import
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="import-history-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Kebijakan Duplikat</th>
                        <th>Status</th>
                        <th class="text-center">Dibuat</th>
                        <th class="text-center">Diperbarui</th>
                        <th class="text-center">Dilewati</th>
                        <th class="text-center">Error</th>
                        <th>Waktu</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($batches as $batch)
                        <tr>
                            <td class="small">
                                <i class="bi bi-file-earmark-spreadsheet me-1 text-muted"></i>
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
                            <td>
                                <a href="{{ route('imports.show', $batch) }}" class="btn btn-xs btn-outline-secondary btn-sm py-0">
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
document.getElementById('form-upload').addEventListener('submit', function () {
    var btn = document.getElementById('btn-upload');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memuat pratinjau...';
    btn.disabled = true;
});
</script>
@endpush
