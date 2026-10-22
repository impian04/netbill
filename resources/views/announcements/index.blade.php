@extends('layouts.app')
@section('title', 'Pengumuman')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Pengumuman</h1>
        <p class="text-muted small mb-0">Diakses oleh pelanggan melalui aplikasi mobile</p>
    </div>
    <a href="{{ route('announcements.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-plus-lg"></i>Tambah
    </a>
</div>

@if (session('success'))
    <div class="alert alert-success d-flex gap-2 py-2 mb-3">
        <i class="bi bi-check-circle-fill"></i>{{ session('success') }}
    </div>
@endif

<div class="card">
    <div class="card-body p-0">
        @if ($announcements->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-megaphone fs-1 d-block mb-2 opacity-25"></i>
                <p class="fw-semibold mb-0">Belum ada pengumuman.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="announcements-table">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th class="text-center">Aktif</th>
                            <th>Tayang</th>
                            <th>Kedaluwarsa</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($announcements as $a)
                            <tr>
                                <td class="fw-medium">{{ $a->title }}</td>
                                <td class="text-center">
                                    @if ($a->is_active)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $a->published_at ? tgl_indo($a->published_at) : '—' }}</td>
                                <td class="small text-muted">{{ $a->expires_at ? tgl_indo($a->expires_at) : '—' }}</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('announcements.edit', $a) }}"
                                           class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                        <button class="btn btn-sm btn-outline-danger btn-delete"
                                                data-name="{{ $a->title }}"
                                                data-action="{{ route('announcements.destroy', $a) }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($announcements->hasPages())
                <div class="d-flex justify-content-center py-3 border-top">
                    {{ $announcements->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
</div>

@include('layouts.partials.confirm-delete-modal')
@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-delete').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('delete-modal-name').textContent = this.dataset.name;
        document.getElementById('delete-form').action = this.dataset.action;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
