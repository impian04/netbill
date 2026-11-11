@extends('layouts.app')

@section('title', 'Pengumuman')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Pengumuman Pelanggan</h1>
        <p class="page-subtitle">Kelola pesan dan informasi operasional yang tampil pada aplikasi mobile pelanggan</p>
    </div>
    <div>
        <button type="button" class="btn btn-sm btn-primary" id="btn-open-create-announcement">
            <i class="bi bi-plus-lg me-1"></i>Buat Pengumuman
        </button>
    </div>
</div>

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form id="filter-announcements-form" class="row g-2 align-items-center">
            <div class="col-12 col-lg-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" id="filter-ann-search" name="search" class="form-control form-control-sm"
                           placeholder="Cari judul atau isi pengumuman...">
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <select name="status" id="filter-ann-status" class="form-select form-select-sm">
                    <option value="">Semua Status Tayang</option>
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>

            <div class="col-6 col-lg-2 ms-lg-auto text-end">
                <button type="button" id="btn-reset-ann-filter" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<div class="card">
    <div class="card-body p-3">
        <table id="announcements-table" class="table table-hover align-middle mb-0 w-100">
            <thead>
                <tr>
                    <th>Judul Pengumuman</th>
                    <th class="text-center" style="width: 100px;">Status</th>
                    <th>Tayang Mulai</th>
                    <th>Kedaluwarsa</th>
                    <th class="d-none d-lg-table-cell">Dibuat Pada</th>
                    <th class="text-end" style="width: 80px;">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

{{-- ── Offcanvas Form Pengumuman (Tambah & Ubah) ────────────────── --}}
@include('announcements.partials.offcanvas')

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Inisialisasi DataTable Pengumuman Server-Side
    const table = window.initServerTable('#announcements-table', {
        ajaxUrl: '{{ route('announcements.index') }}',
        filterForm: '#filter-announcements-form',
        searchInput: '#filter-ann-search',
        resetButton: '#btn-reset-ann-filter',
        pageLength: 15,
        order: [[4, 'desc']], // default created_at desc
        columns: [
            {
                data: 'title',
                name: 'title',
                render: function (data, type, row) {
                    return `
                        <div>
                            <span class="fw-semibold text-dark">${window.escapeHtml(data)}</span>
                            <div class="small text-muted text-truncate" style="max-width: 380px;">${window.escapeHtml(row.body || '')}</div>
                        </div>
                    `;
                }
            },
            {
                data: 'is_active',
                name: 'is_active',
                className: 'text-center',
                render: function (data) {
                    if (data) {
                        return '<span class="badge bg-success">Aktif</span>';
                    }
                    return '<span class="badge bg-secondary">Nonaktif</span>';
                }
            },
            {
                data: 'published_at',
                name: 'published_at',
                className: 'small text-muted',
                render: function (data) {
                    return data ? window.formatTanggalIndo(data) : '<span class="text-muted">Langsung</span>';
                }
            },
            {
                data: 'expires_at',
                name: 'expires_at',
                className: 'small text-muted',
                render: function (data) {
                    return data ? window.formatTanggalIndo(data) : '<span class="text-muted">Selamanya</span>';
                }
            },
            {
                data: 'created_at',
                name: 'created_at',
                className: 'd-none d-lg-table-cell small text-muted',
                render: function (data) {
                    return window.formatTanggalIndo(data);
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-end text-nowrap',
                render: function (data, type, row) {
                    return `
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary py-1 px-2 btn-edit-announcement"
                                    data-id="${row.id}" data-url="${row.urls.show}" title="Ubah Pengumuman">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger py-1 px-2 btn-delete-announcement"
                                    data-url="${row.urls.delete}" data-title="${window.escapeHtml(row.title)}" title="Hapus Pengumuman">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    // 2. Inisialisasi CRUD Offcanvas Pengumuman
    const crudAnnouncement = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-announcement',
        storeUrl: '{{ route('announcements.store') }}',
        baseUrl: '{{ url('announcements') }}',
        createTitle: 'Buat Pengumuman Baru',
        editTitle: 'Ubah Data Pengumuman',
        table: table,
    });

    // Tombol Buka Tambah Pengumuman
    document.getElementById('btn-open-create-announcement')?.addEventListener('click', function () {
        crudAnnouncement.openCreate();
    });

    // Tombol Ubah Pengumuman (Event Delegation)
    $('#announcements-table').on('click', '.btn-edit-announcement', function () {
        const id = $(this).data('id');
        const url = $(this).data('url');
        crudAnnouncement.openEdit(id, url);
    });

    // Tombol Hapus Pengumuman (Event Delegation)
    $('#announcements-table').on('click', '.btn-delete-announcement', function () {
        const url = $(this).data('url');
        const title = $(this).data('title');
        crudAnnouncement.deleteItem(url, `Hapus pengumuman "${title}"?`);
    });
});
</script>
@endpush
