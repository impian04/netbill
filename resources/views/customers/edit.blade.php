@extends('layouts.app')

@section('title', 'Edit Pelanggan')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
    <div>
        <h1 class="h4 fw-bold mb-0">Edit Pelanggan</h1>
        <p class="text-muted small mb-0 font-monospace">{{ $customer->customer_code }}</p>
    </div>
</div>

<div class="card" style="max-width:860px;">
    <div class="card-header">
        <i class="bi bi-pencil me-2"></i>Edit Data Pelanggan
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('customers.update', $customer) }}" id="form-edit-customer" novalidate>
            @csrf
            @method('PUT')
            @include('customers._form')
            <div class="d-flex gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary" id="btn-update">
                    <i class="bi bi-check-lg me-1"></i>Perbarui Data
                </button>
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('form-edit-customer').addEventListener('submit', function () {
    var btn = document.getElementById('btn-update');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memperbarui...';
    btn.disabled = true;
});
</script>
@endpush
