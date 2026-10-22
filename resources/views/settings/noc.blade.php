@extends('layouts.app')
@section('title', 'Kontak NOC')
@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Kontak NOC</h1>
        <p class="text-muted small mb-0">Nomor WhatsApp yang ditampilkan di aplikasi pelanggan</p>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success d-flex gap-2 py-2 mb-3">
        <i class="bi bi-check-circle-fill"></i>{{ session('success') }}
    </div>
@endif

<div class="card" style="max-width:600px;">
    <div class="card-header"><i class="bi bi-telephone me-2"></i>Nomor WhatsApp NOC</div>
    <div class="card-body">
        <form method="POST" action="{{ route('settings.noc.update') }}" id="form-noc" novalidate>
            @csrf
            @method('PUT')

            @php
            $labels = [
                'noc_connection_wa'   => ['icon' => 'bi-wifi', 'label' => 'Gangguan Koneksi'],
                'noc_payment_wa'      => ['icon' => 'bi-credit-card', 'label' => 'Pembayaran'],
                'noc_installation_wa' => ['icon' => 'bi-tools', 'label' => 'Pemasangan Baru'],
            ];
            @endphp

            @foreach ($labels as $key => $meta)
                <div class="mb-3">
                    <label for="{{ $key }}" class="form-label fw-semibold">
                        <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
                    </label>
                    <div class="input-group">
                        <span class="input-group-text text-muted">+</span>
                        <input type="tel" id="{{ $key }}" name="{{ $key }}"
                               class="form-control @error($key) is-invalid @enderror"
                               value="{{ old($key, $settings[$key] ?? '') }}"
                               placeholder="6281234567890">
                        @error($key)
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-text">Format: 62xxxxxxxxxx · Kosong = tidak ditampilkan</div>
                </div>
            @endforeach

            <div class="d-flex gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary" id="btn-save-noc">
                    <i class="bi bi-floppy me-1"></i>Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('form-noc').addEventListener('submit', function () {
    var b = document.getElementById('btn-save-noc');
    b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
});
</script>
@endpush
