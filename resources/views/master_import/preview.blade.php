@extends('layouts.app')

@php
    $typeLabel = match ($type) {
        'odc'      => 'ODC (Optical Distribution Cabinet)',
        'odp'      => 'ODP (Optical Distribution Point)',
        'material' => 'Barang / Material',
    };
@endphp

@section('title', 'Pratinjau Import ' . $typeLabel)

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Pratinjau Import {{ $typeLabel }}">
    <x-slot:subtitle>
        Periksa ringkasan berkas sebelum diproses ke database. Maksimal 2.000 baris dalam satu transaksi.
    </x-slot:subtitle>
    <x-slot:actions>
        <x-btn variant="neutral" href="{{ $backRoute }}"  >
            Batal
        </x-btn>
    </x-slot:actions>
</x-page-header>

{{-- ── Kartu Ringkasan Berkas ─────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <x-stat-card border-color="#2563eb" icon-bg="#dbeafe" icon="bi bi-file-earmark-spreadsheet text-primary" :value="$data['total_rows'] . ' Baris'" title="Total Baris Data" help="Nama berkas: {{ $data['original_name'] }}" />
    </div>
    <div class="col-12 col-md-4">
        <x-stat-card border-color="#16a34a" icon-bg="#dcfce7" icon="bi bi-geo-alt text-success" color="success" :value="'POP ' . $data['pop_code']" title="POP Target" help="{{ $data['pop_name'] }}" />
    </div>
    <div class="col-12 col-md-4">
        <x-stat-card border-color="#f59e0b" icon-bg="#fef3c7" icon="bi bi-shield-check text-warning" color="warning" value="Sinkron & Aman" title="Metode Import" help="Duplikat otomatis dilewati" />
    </div>
</div>

{{-- ── Kartu Pratinjau 5 Baris Pertama ────────────────────────── --}}
<x-table-card class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-semibold">
            Pratinjau 5 Baris Pertama
        </div>
        <div class="text-muted small">
            Menampilkan {{ count($data['preview']) }} dari {{ $data['total_rows'] }} total baris
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;" class="text-center">#</th>
                    @foreach ($data['headers'] as $header)
                        <th class="font-monospace text-uppercase small">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($data['preview'] as $idx => $row)
                    <tr>
                        <td class="text-center text-muted small">{{ $idx + 1 }}</td>
                        @foreach ($data['headers'] as $hIdx => $hName)
                            <td class="small">{{ $row[$hIdx] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="alert alert-info mt-3 mb-0 d-flex gap-2 py-2">
        <div>
            <strong>Catatan:</strong> Data dengan nama yang sudah ada di POP ini akan <strong>dilewati</strong> secara otomatis dan dilaporkan pada ringkasan hasil import.
        </div>
    </div>
</x-table-card>

{{-- ── Form Konfirmasi Eksekusi Import ─────────────────────────── --}}
<div class="card">
    <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">
            Pastikan kolom dan format data sudah sesuai sebelum menekan tombol konfirmasi.
        </div>
        <form method="POST" action="{{ $confirmRoute }}" id="form-confirm-master-import" class="d-flex gap-2">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-btn variant="neutral" href="{{ $backRoute }}"  >
                Batal
            </x-btn>
            <x-btn type="submit" variant="primary"     id="btn-submit-confirm">
                Proses Import Sekarang
            </x-btn>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-confirm-master-import');
    const submitBtn = document.getElementById('btn-submit-confirm');
    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses Import...';
        });
    }
});
</script>
@endpush

