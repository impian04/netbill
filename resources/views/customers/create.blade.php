@extends('layouts.app')

@section('title', 'Tambah Pelanggan')

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
    <div>
        <h1 class="h4 fw-bold mb-0">Tambah Pelanggan</h1>
        <p class="text-muted small mb-0">Isi data pelanggan baru</p>
    </div>
</div>

<div class="card" style="max-width:860px;">
    <div class="card-header">
        <i class="bi bi-person-plus me-2"></i>Data Pelanggan
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('customers.store') }}" id="form-create-customer" novalidate>
            @csrf
            @include('customers._form')
            <div class="d-flex gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary" id="btn-simpan">
                    <i class="bi bi-check-lg me-1"></i>Simpan Pelanggan
                </button>
                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
var nextCodeMap = @json([$nextCode ?? '']);
var defaultPrefix = '{{ addslashes(strtoupper('SLX-GS')) }}';

function suggestCode(prefix) {
    prefix = prefix.toUpperCase().trim();
    if (!prefix) return;
    fetch('/customers?suggest_code=1&prefix=' + encodeURIComponent(prefix))
        .catch(function () {}); // best-effort; field stays editable anyway
}

document.getElementById('form-create-customer').addEventListener('submit', function () {
    var btn = document.getElementById('btn-simpan');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
    btn.disabled = true;
});
</script>
@endpush
