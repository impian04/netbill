@extends('layouts.app')
@section('title', 'Tambah Pengumuman')
@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('announcements.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
    <h1 class="h4 fw-bold mb-0">Tambah Pengumuman</h1>
</div>

<div class="card" style="max-width:720px;">
    <div class="card-body">
        <form method="POST" action="{{ route('announcements.store') }}" id="form-ann" novalidate>
            @csrf
            @include('announcements._form')
            <div class="d-flex gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary" id="btn-save-ann">
                    <i class="bi bi-check-lg me-1"></i>Simpan
                </button>
                <a href="{{ route('announcements.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('form-ann').addEventListener('submit', function () {
    var b = document.getElementById('btn-save-ann');
    b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
});
</script>
@endpush
