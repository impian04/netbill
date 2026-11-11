@extends('layouts.app')

@section('title', 'Tagihan')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Daftar Tagihan</h1>
        <p class="page-subtitle">Pantau seluruh tagihan pelanggan, status pelunasan, dan jatuh tempo</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('export.invoices') }}" class="btn btn-sm btn-success" id="btn-export-all-invoices">
            <i class="bi bi-download me-1"></i>Ekspor XLSX
        </a>
        <button type="button" class="btn btn-sm btn-primary" id="btn-open-create-invoice">
            <i class="bi bi-plus-lg me-1"></i>Tambah Tagihan
        </button>
    </div>
</div>

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form id="filter-invoices-form" class="row g-2 align-items-center">
            <div class="col-12 col-lg-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" id="filter-inv-search" name="search" class="form-control form-control-sm"
                           placeholder="Cari nomor, kode, atau nama...">
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <select name="status" id="filter-inv-status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="PAID">Lunas</option>
                    <option value="UNPAID">Belum Bayar</option>
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <input type="month" name="period" id="filter-inv-period" class="form-control form-control-sm">
            </div>

            <div class="col-6 col-lg-2">
                <select name="year" id="filter-inv-year" class="form-select form-select-sm">
                    <option value="">Semua Tahun</option>
                    @foreach ($years ?? [] as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-3 text-end">
                <button type="button" id="btn-reset-invoices-filter" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<div class="card">
    <div class="card-body p-3">
        <table id="global-invoices-table" class="table table-hover align-middle mb-0 w-100">
            <thead>
                <tr>
                    <th>No. Tagihan</th>
                    <th>Pelanggan</th>
                    <th>Periode</th>
                    <th>Jatuh Tempo</th>
                    <th class="text-end">Nominal Tagihan</th>
                    <th>Status</th>
                    <th class="d-none d-lg-table-cell">Waktu Bayar</th>
                    <th class="text-end" style="width: 80px;">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

{{-- ── Offcanvas Form Tagihan (Tambah & Ubah) ───────────────────── --}}
@include('invoices.partials.offcanvas', [
    'customers' => $customers ?? [],
])

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Inisialisasi DataTable Tagihan Server-Side
    const table = window.initServerTable('#global-invoices-table', {
        ajaxUrl: '{{ route('invoices.index') }}',
        filterForm: '#filter-invoices-form',
        searchInput: '#filter-inv-search',
        resetButton: '#btn-reset-invoices-filter',
        pageLength: 15,
        order: [[2, 'desc']], // default period desc
        columns: [
            {
                data: 'invoice_number',
                name: 'invoice_number',
                render: function (data) {
                    return `<span class="font-monospace small text-primary fw-semibold">${window.escapeHtml(data || '—')}</span>`;
                }
            },
            {
                data: 'customer_name',
                name: 'customer_name',
                render: function (data, type, row) {
                    if (!data) return '<span class="text-muted">—</span>';
                    return `
                        <div>
                            <span class="fw-semibold text-dark">${window.escapeHtml(data)}</span>
                            <div class="font-monospace small text-muted">${window.escapeHtml(row.customer_code || '')}</div>
                        </div>
                    `;
                }
            },
            {
                data: 'period',
                name: 'period',
                render: function (data) {
                    return `<span class="fw-semibold">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'due_date',
                name: 'due_date',
                render: function (data) {
                    return data ? window.formatTanggalIndo(data) : '<span class="text-muted">—</span>';
                }
            },
            {
                data: 'deferred_amount',
                name: 'deferred_amount',
                className: 'text-end fw-semibold',
                render: function (data) {
                    return window.formatRupiah(data);
                }
            },
            {
                data: 'status',
                name: 'status',
                render: function (data) {
                    if (data === 'PAID') {
                        return '<span class="badge badge-paid px-2 py-1"><i class="bi bi-check-circle me-1"></i>Lunas</span>';
                    }
                    return '<span class="badge badge-unpaid px-2 py-1"><i class="bi bi-clock me-1"></i>Belum Bayar</span>';
                }
            },
            {
                data: 'paid_at',
                name: 'paid_at',
                className: 'd-none d-lg-table-cell small text-muted',
                render: function (data) {
                    return data ? window.formatTanggalIndo(data) : '<span class="text-muted">—</span>';
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-end text-nowrap',
                render: function (data, type, row) {
                    return `
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary py-1 px-2 btn-edit-invoice"
                                    data-id="${row.id}" data-url="${row.urls.show}" title="Ubah Tagihan">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger py-1 px-2 btn-delete-invoice"
                                    data-url="${row.urls.delete}" data-period="${window.escapeHtml(row.period)}" title="Hapus Tagihan">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    // 2. Inisialisasi CRUD Offcanvas Tagihan
    const crudInvoice = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-invoice',
        storeUrl: '{{ route('invoices.store') }}',
        baseUrl: '{{ url('invoices') }}',
        createTitle: 'Tambah Tagihan Baru',
        editTitle: 'Ubah Data Tagihan',
        table: table,
        onOpenCreate: function () {
            const customerField = document.getElementById('invoice-customer-field');
            if (customerField) customerField.style.display = 'block';
        },
        onOpenEdit: function () {
            const customerField = document.getElementById('invoice-customer-field');
            if (customerField) customerField.style.display = 'block';
        }
    });

    // Tombol Tambah Tagihan
    document.getElementById('btn-open-create-invoice')?.addEventListener('click', function () {
        crudInvoice.openCreate();
    });

    // Tombol Ubah Tagihan (Event Delegation)
    $('#global-invoices-table').on('click', '.btn-edit-invoice', function () {
        const id = $(this).data('id');
        const url = $(this).data('url');
        crudInvoice.openEdit(id, url);
    });

    // Tombol Hapus Tagihan (Event Delegation)
    $('#global-invoices-table').on('click', '.btn-delete-invoice', function () {
        const url = $(this).data('url');
        const period = $(this).data('period');
        crudInvoice.deleteItem(url, `Hapus tagihan periode ${period}?`);
    });
});
</script>
@endpush
