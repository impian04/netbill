@extends('layouts.app')

@section('title', 'Pengumuman')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Pengumuman Pelanggan" subtitle="Kelola pesan dan informasi operasional yang tampil pada aplikasi mobile pelanggan">
    <x-slot:actions>
        <x-btn variant="primary" id="btn-open-create-announcement">Buat Pengumuman</x-btn>
    </x-slot:actions>
</x-page-header>

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<x-filter-card>
    <form id="filter-announcements-form" class="row g-2 align-items-center">
        <div class="col-12 col-lg-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"></span>
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
            <x-btn variant="neutral" id="btn-reset-ann-filter" class="w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
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
</x-table-card>

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
                render: function (data) { return window.statusBadge('active_status', data ? 'ACTIVE' : 'INACTIVE'); }
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
                    return `<div class="d-flex justify-content-end gap-1">
                        ${window.btnIconHtml('edit', { class: 'btn-edit-announcement', 'data-id': row.id, 'data-url': row.urls.show, title: 'Ubah Pengumuman' })}
                        ${window.btnIconHtml('delete', { class: 'btn-delete-announcement', 'data-url': row.urls.delete, 'data-title': window.escapeHtml(row.title), title: 'Hapus Pengumuman' })}
                    </div>`;
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
