@extends('layouts.app')

@section('title', 'Manajemen POP')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Manajemen Point of Presence (POP)" subtitle="Kelola wilayah layanan jaringan PT JCN dan kode penomoran pelanggan">
    <x-slot:actions>
        <x-btn variant="primary"     id="btn-open-create-pop">
            Tambah POP
        </x-btn>
    </x-slot:actions>
</x-page-header>

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<x-filter-card>
    <form id="filter-pops-form" class="row g-2 align-items-center">
        <div class="col-12 col-lg-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"></span>
                <input type="search" id="filter-pop-search" name="search" class="form-control form-control-sm"
                       placeholder="Cari kode, nama, atau kode VA...">
            </div>
        </div>

        <div class="col-6 col-lg-2 ms-lg-auto text-end">
            <x-btn variant="neutral" id="btn-reset-pop-filter" class="w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
    <table id="pops-table" class="table table-hover align-middle mb-0 w-100">
        <thead>
            <tr>
                <th>Kode POP</th>
                <th>Nama POP</th>
                <th>Kode VA</th>
                <th class="text-center">Pelanggan</th>
                <th class="text-center">Status</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</x-table-card>

{{-- ── Offcanvas Form POP (Tambah & Ubah) ───────────────────────── --}}
@include('pops.partials.offcanvas')

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Inisialisasi DataTable POP Server-Side
    const table = window.initServerTable('#pops-table', {
        ajaxUrl: '{{ route('pops.index') }}',
        filterForm: '#filter-pops-form',
        searchInput: '#filter-pop-search',
        resetButton: '#btn-reset-pop-filter',
        pageLength: 15,
        columns: [
            {
                data: 'code',
                name: 'code',
                render: function (data) {
                    return `<span class="badge bg-secondary font-monospace fs-7">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'name',
                name: 'name',
                render: function (data) {
                    return `<span class="fw-semibold text-dark">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'va_code',
                name: 'va_code',
                render: function (data) {
                    return `<span class="font-monospace text-muted">${window.escapeHtml(data || '-')}</span>`;
                }
            },
            {
                data: 'customers_count',
                name: 'customers_count',
                className: 'text-center',
                render: function (data) {
                    return `<span class="badge rounded-pill bg-light text-dark border">${data} pelanggan</span>`;
                }
            },
            {
                data: 'is_active',
                name: 'is_active',
                className: 'text-center',
                render: function (data) { return window.statusBadge('active_status', data ? 'ACTIVE' : 'INACTIVE'); }
            },
            {
                data: null,
                orderable: false,
                className: 'text-end text-nowrap',
                render: function (data, type, row) {
                    const editBtn = window.btnIconHtml('edit', {
                        'class': 'btn-edit-pop',
                        'data-id': row.id,
                        'data-url': row.urls.show,
                        'title': 'Ubah POP'
                    });
                    const deleteAttrs = {
                        'class': 'btn-delete-pop',
                        'data-url': row.urls.delete,
                        'data-code': row.code
                    };
                    if (row.customers_count > 0) {
                        deleteAttrs['disabled'] = 'disabled';
                        deleteAttrs['title'] = 'Tidak dapat dihapus karena memiliki pelanggan';
                    } else {
                        deleteAttrs['title'] = 'Hapus POP';
                    }
                    const deleteBtn = window.btnIconHtml('delete', deleteAttrs);

                    return `<div class="btn-group btn-group-sm">${editBtn}${deleteBtn}</div>`;
                }
            }
        ]
    });

    // 2. Inisialisasi CRUD Offcanvas POP
    const codeInput = document.getElementById('pop_code');
    const codeHelp = document.getElementById('pop_code_help');

    const crudPop = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-pop',
        storeUrl: '{{ route('pops.store') }}',
        baseUrl: '{{ url('pops') }}',
        createTitle: 'Tambah POP Baru',
        editTitle: 'Ubah Data POP',
        table: table,
        onOpenCreate: function (form) {
            if (codeInput) {
                codeInput.removeAttribute('readonly');
                codeInput.classList.remove('bg-light');
            }
            if (codeHelp) {
                codeHelp.textContent = '2-4 huruf kapital, unik. Tidak dapat diubah setelah memiliki pelanggan.';
            }
        },
        onOpenEdit: function (form, record) {
            if (codeInput) {
                if (record.has_customers) {
                    codeInput.setAttribute('readonly', 'readonly');
                    codeInput.classList.add('bg-light');
                    if (codeHelp) {
                        codeHelp.textContent = 'Kode POP tidak dapat diubah karena sudah memiliki data pelanggan.';
                    }
                } else {
                    codeInput.removeAttribute('readonly');
                    codeInput.classList.remove('bg-light');
                    if (codeHelp) {
                        codeHelp.textContent = '2-4 huruf kapital, unik.';
                    }
                }
            }
        }
    });

    // Tombol Buka Tambah POP
    document.getElementById('btn-open-create-pop')?.addEventListener('click', function () {
        crudPop.openCreate();
    });

    // Tombol Ubah POP (Event Delegation)
    $('#pops-table').on('click', '.btn-edit-pop', function () {
        const id = $(this).data('id');
        const url = $(this).data('url');
        crudPop.openEdit(id, url);
    });

    // Tombol Hapus POP (Event Delegation)
    $('#pops-table').on('click', '.btn-delete-pop', function () {
        if ($(this).is(':disabled')) return;
        const url = $(this).data('url');
        const code = $(this).data('code');
        crudPop.deleteItem(url, `Hapus POP "${code}"?`);
    });
});
</script>
@endpush

