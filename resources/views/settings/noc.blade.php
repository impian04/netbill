@extends('layouts.app')

@section('title', 'Kontak NOC')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Pengaturan Kontak NOC</h1>
        <p class="page-subtitle">Kelola nomor WhatsApp customer service dan bantuan teknis pada aplikasi mobile</p>
    </div>
</div>

<!-- <div class="card" style="max-width: 600px;"> -->
<div class="card">
    <div class="card-header">
        Nomor WhatsApp Kontak Dukungan
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('settings.noc.update') }}" id="form-noc" novalidate>
            @csrf
            @method('PUT')

            @php
            $labels = [
                'noc_connection_wa'   => ['icon' => 'bi-wifi', 'label' => 'Bantuan Kendala Koneksi (NOC)'],
                'noc_payment_wa'      => ['icon' => 'bi-credit-card', 'label' => 'Bantuan Pembayaran & Tagihan'],
                'noc_installation_wa' => ['icon' => 'bi-tools', 'label' => 'Bantuan Pemasangan Baru / Marketing'],
            ];
            @endphp

            @foreach ($labels as $key => $meta)
                <div class="mb-3">
                    <label for="{{ $key }}" class="form-label small fw-semibold">
                        <i class="bi {{ $meta['icon'] }} me-1 text-primary"></i>{{ $meta['label'] }}
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text text-muted">+</span>
                        <input type="tel" id="{{ $key }}" name="{{ $key }}"
                               class="form-control form-control-sm @error($key) is-invalid @enderror"
                               value="{{ old($key, $settings[$key] ?? '') }}"
                               placeholder="6281234567890">
                        @error($key)
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-text">Format: 62xxxxxxxxxx (kosongkan jika tidak ingin ditampilkan).</div>
                </div>
            @endforeach

            <div class="d-flex gap-2 mt-4 pt-3 border-top">
                <x-btn type="submit" variant="primary"     id="btn-save-noc">
                    Simpan Perubahan
                </x-btn>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('form-noc')?.addEventListener('submit', function () {
        var b = document.getElementById('btn-save-noc');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
    });
});
</script>
@endpush
