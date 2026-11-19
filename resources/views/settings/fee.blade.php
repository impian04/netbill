@extends('layouts.app')

@section('title', 'Pengaturan Fee Marketing')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Pengaturan Fee Marketing</h1>
        <p class="page-subtitle">Kelola nominal bawaan komisi/fee marketing saat pelanggan referral pertama kali aktif</p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Nominal Bawaan Fee Marketing
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('settings.fee.update') }}" novalidate>
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="marketing_fee_default" class="form-label small fw-semibold">
                    Fee Marketing Bawaan (Rp)
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Rp</span>
                    <input type="number" id="marketing_fee_default" name="marketing_fee_default"
                           class="form-control form-control-sm @error('marketing_fee_default') is-invalid @enderror"
                           value="{{ old('marketing_fee_default', $defaultFee) }}"
                           placeholder="Contoh: 50000" min="0">
                    @error('marketing_fee_default')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-text">
                    Nilai ini otomatis terisi saat pelanggan referral aktif pertama kali. Admin tetap dapat mengubah nominal per transaksi di halaman Fee Marketing. Boleh dikosongkan.
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-3 border-top">
                <x-btn type="submit" variant="primary"    >
                    Simpan Pengaturan
                </x-btn>
            </div>
        </form>
    </div>
</div>
@endsection

