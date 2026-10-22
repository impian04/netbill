@extends('layouts.app')

@section('title', 'Tambah Tagihan')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
    <div>
        <h1 class="h4 fw-bold mb-0">Tambah Tagihan</h1>
        <p class="text-muted small mb-0">
            <code class="text-primary">{{ $customer->customer_code }}</code> &mdash; {{ $customer->name }}
        </p>
    </div>
</div>

<div class="card" style="max-width:700px;">
    <div class="card-header">
        <i class="bi bi-receipt me-2"></i>Data Tagihan Baru
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('customers.invoices.store', $customer) }}"
              id="form-create-invoice" novalidate>
            @csrf
            @include('invoices._form')
            <div class="d-flex gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary" id="btn-simpan-invoice">
                    <i class="bi bi-check-lg me-1"></i>Simpan Tagihan
                </button>
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('form-create-invoice').addEventListener('submit', function () {
    var btn = document.getElementById('btn-simpan-invoice');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
    btn.disabled = true;
});
</script>
@endpush
