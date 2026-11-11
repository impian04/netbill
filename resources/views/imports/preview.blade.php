@extends('layouts.app')

@section('title', 'Pratinjau Import')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('imports.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Batal
    </a>
    <div>
        <h1 class="h4 fw-bold mb-0">Pratinjau Import</h1>
        <p class="text-muted small mb-0">
            <code>{{ $originalName }}</code> &mdash;
            <strong>{{ number_format($preview['total_rows']) }}</strong> baris data
            (menampilkan {{ count($preview['rows']) }} pertama)
        </p>
    </div>
</div>

{{-- Preview table --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table me-2"></i>Pratinjau Data (10 Baris Pertama)</span>
        @if ($preview['total_rows'] > 10)
            <span class="badge bg-secondary">+{{ $preview['total_rows'] - 10 }} baris lainnya</span>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:360px;overflow-y:auto;">
            <table class="table table-sm table-hover mb-0" style="font-size:.8rem;" id="preview-table">
                <thead class="sticky-top">
                    <tr>
                        <th class="text-muted" style="width:40px;">#</th>
                        @foreach ($preview['headers'] as $h)
                            <th>{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preview['rows'] as $i => $row)
                        <tr>
                            <td class="text-muted">{{ $i + 2 }}</td>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="99" class="text-center text-muted py-3">File tidak memiliki baris data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Confirmation form --}}
<div class="card">
    <div class="card-header">
        <i class="bi bi-gear me-2"></i>Konfigurasi Import
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('imports.confirm') }}" id="form-confirm" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="file_path" value="{{ $filePath }}">
            <input type="hidden" name="original_name" value="{{ $originalName }}">

            {{-- Duplicate policy --}}
            <div class="mb-4">
                <label class="form-label fw-semibold">
                    Kebijakan Duplikat
                    <span class="text-danger">*</span>
                </label>
                <div class="text-muted small mb-2">
                    Duplikat = baris dengan ID Pelanggan + Periode yang sudah ada di database.
                </div>

                <div class="d-flex flex-column gap-2">
                    <label class="d-flex align-items-start gap-3 p-3 border rounded cursor-pointer"
                           style="cursor:pointer;" id="policy-skip-label">
                        <input type="radio" name="duplicate_policy" value="skip"
                               id="policy-skip" class="form-check-input flex-shrink-0" checked>
                        <div>
                            <div class="fw-semibold">
                                <span class="badge bg-secondary me-1">Lewati</span>
                                Biarkan data lama
                            </div>
                            <div class="text-muted small">
                                Baris duplikat tidak diproses. Data pelanggan &amp; tagihan yang sudah
                                ada tidak berubah. Cocok untuk import tambahan.
                            </div>
                        </div>
                    </label>

                    <label class="d-flex align-items-start gap-3 p-3 border rounded cursor-pointer"
                           style="cursor:pointer;" id="policy-update-label">
                        <input type="radio" name="duplicate_policy" value="update"
                               id="policy-update" class="form-check-input flex-shrink-0">
                        <div>
                            <div class="fw-semibold">
                                <span class="badge bg-primary me-1">Perbarui</span>
                                Timpa dengan data baru
                            </div>
                            <div class="text-muted small">
                                Tagihan yang sudah ada akan diperbarui (status &amp; nominal).
                                Data pelanggan juga diperbarui. Cocok untuk sinkronisasi ulang.
                            </div>
                        </div>
                    </label>
                </div>
                @error('duplicate_policy')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Summary before confirm --}}
            <div class="alert alert-warning d-flex gap-2 py-2 mb-3" style="font-size:.83rem;">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                <span>
                    Proses akan menjalankan <strong>{{ number_format($preview['total_rows']) }}</strong> baris
                    di background. Halaman status akan diperbarui otomatis. Jangan tutup browser sampai selesai.
                </span>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" id="btn-confirm" class="btn btn-primary">
                    <i class="bi bi-play-circle me-1"></i>Mulai Import
                </button>
                <a href="{{ route('imports.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Highlight selected policy card
document.querySelectorAll('input[name="duplicate_policy"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('label[id^="policy-"]').forEach(function (lbl) {
            lbl.style.borderColor = '';
            lbl.style.background  = '';
        });
        var lbl = document.getElementById('policy-' + this.value + '-label');
        lbl.style.borderColor = 'var(--nb-accent)';
        lbl.style.background  = 'rgba(59,130,246,.05)';
    });
    // Init for checked
    if (radio.checked) radio.dispatchEvent(new Event('change'));
});

// Prevent double submit
document.getElementById('form-confirm').addEventListener('submit', function () {
    var btn = document.getElementById('btn-confirm');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';
    btn.disabled = true;
});
</script>
@endpush
