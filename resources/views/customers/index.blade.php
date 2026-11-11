@extends('layouts.app')

@section('title', 'Pelanggan')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Daftar Pelanggan</h1>
        <p class="page-subtitle">Kelola data pelanggan internet, paket layanan, dan aktivasi akun</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('export.customers') }}" class="btn btn-sm btn-success" id="btn-export-customers">
            <i class="bi bi-download me-1"></i>Ekspor XLSX
        </a>
        <button type="button" class="btn btn-sm btn-primary" id="btn-open-create-customer">
            <i class="bi bi-plus-lg me-1"></i>Tambah Pelanggan
        </button>
    </div>
</div>

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form id="filter-customers-form" class="row g-2 align-items-center">
            <div class="col-12 col-lg-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" id="filter-search" name="search" class="form-control form-control-sm"
                           placeholder="Cari kode, nama, atau alamat (otomatis)...">
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <select name="service_status" id="filter-service-status" class="form-select form-select-sm">
                    <option value="">Semua Status Layanan</option>
                    @foreach (\App\Models\Customer::SERVICE_STATUSES as $ss)
                        <option value="{{ $ss }}">{{ $ss }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <select name="package" id="filter-package" class="form-select form-select-sm">
                    <option value="">Semua Paket</option>
                    @foreach ($packages as $pkg)
                        <option value="{{ $pkg }}">{{ $pkg }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <select name="billing_type" id="filter-billing-type" class="form-select form-select-sm">
                    <option value="">Semua Jenis Tagihan</option>
                    @foreach ($billingTypes as $bt)
                        <option value="{{ $bt }}">{{ $bt }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-2 text-end">
                <button type="button" id="btn-reset-filter" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<div class="card">
    <div class="card-body p-3">
        <table id="customers-table" class="table table-hover align-middle mb-0 w-100">
            <thead>
                <tr>
                    <th>ID Pelanggan</th>
                    <th>Nama</th>
                    <th class="d-none d-lg-table-cell">Telepon</th>
                    <th>Paket</th>
                    <th class="d-none d-lg-table-cell">Tagihan</th>
                    <th>Status Layanan</th>
                    <th class="d-none d-lg-table-cell">Terdaftar</th>
                    <th class="text-end" style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

{{-- ── Offcanvas Form (Tambah & Ubah) ───────────────────────────── --}}
@include('customers.partials.offcanvas', [
    'packages'     => $packages,
    'billingTypes' => $billingTypes,
])

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Inisialisasi DataTable Server-Side
    const table = window.initServerTable('#customers-table', {
        ajaxUrl: '{{ route('customers.index') }}',
        filterForm: '#filter-customers-form',
        searchInput: '#filter-search',
        resetButton: '#btn-reset-filter',
        pageLength: 15,
        order: [[0, 'asc']], // default customer_code asc
        columns: [
            {
                data: 'customer_code',
                name: 'customer_code',
                render: function (data, type, row) {
                    return `<span class="font-monospace fw-semibold text-primary">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'name',
                name: 'name',
                render: function (data, type, row) {
                    return `
                        <div>
                            <a href="${row.urls.show}" class="fw-semibold text-dark text-decoration-none hover-primary">
                                ${window.escapeHtml(data)}
                            </a>
                            <div class="small text-muted d-lg-none">${window.escapeHtml(row.phone || '—')}</div>
                        </div>
                    `;
                }
            },
            {
                data: 'phone',
                name: 'phone',
                className: 'd-none d-lg-table-cell small text-muted',
                render: function (data) {
                    return data ? window.escapeHtml(data) : '<span class="text-muted">—</span>';
                }
            },
            {
                data: 'package',
                name: 'package',
                render: function (data) {
                    return `<span class="badge bg-light text-dark border fw-normal">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'billing_type',
                name: 'billing_type',
                className: 'd-none d-lg-table-cell small',
                render: function (data, type, row) {
                    let unpaidBadge = '';
                    if (row.unpaid_count > 0) {
                        unpaidBadge = `<span class="badge badge-unpaid ms-1" title="${row.unpaid_count} tagihan belum bayar">${row.unpaid_count} unpaid</span>`;
                    }
                    return `${window.escapeHtml(data)} ${unpaidBadge}`;
                }
            },
            {
                data: 'service_status',
                name: 'service_status',
                render: function (data) {
                    let color = 'secondary';
                    if (data === 'ACTIVE') color = 'success';
                    else if (data === 'SUSPENDED') color = 'warning';
                    else if (data === 'INACTIVE') color = 'danger';
                    else if (data === 'PENDING') color = 'info';

                    return `<span class="badge bg-${color}">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'subscribed_at',
                name: 'subscribed_at',
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
                            <a href="${row.urls.show}" class="btn btn-outline-secondary py-1 px-2" title="Lihat Rincian">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button type="button" class="btn btn-outline-secondary py-1 px-2 btn-edit-customer"
                                    data-id="${row.id}" data-url="${row.urls.show}" title="Ubah Data">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger py-1 px-2 btn-delete-customer"
                                    data-url="${row.urls.delete}" data-name="${window.escapeHtml(row.name)}" title="Hapus Pelanggan">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    // 2. Inisialisasi CRUD Offcanvas Pelanggan
    const crudCustomer = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-customer',
        storeUrl: '{{ route('customers.store') }}',
        baseUrl: '{{ url('customers') }}',
        createTitle: 'Tambah Pelanggan',
        editTitle: 'Ubah Data Pelanggan',
        table: table,
        onOpenCreate: function (form) {
            // Saran ID otomatis
            fetchNextCode('SLX-GS');
        }
    });

    // Tombol Buka Tambah Pelanggan
    document.getElementById('btn-open-create-customer')?.addEventListener('click', function () {
        crudCustomer.openCreate();
    });

    // Tombol Ubah Pelanggan (Event Delegation)
    $('#customers-table').on('click', '.btn-edit-customer', function () {
        const id = $(this).data('id');
        const url = $(this).data('url');
        crudCustomer.openEdit(id, url);
    });

    // Tombol Hapus Pelanggan (Event Delegation)
    $('#customers-table').on('click', '.btn-delete-customer', function () {
        const url = $(this).data('url');
        const name = $(this).data('name');
        crudCustomer.deleteItem(url, `Hapus pelanggan "${name}" beserta seluruh riwayat tagihannya?`);
    });

    // Helper saran ID otomatis saat ganti prefix
    const prefixInput = document.getElementById('cust_code_prefix');
    if (prefixInput) {
        prefixInput.addEventListener('input', function () {
            fetchNextCode(this.value);
        });
    }

    function fetchNextCode(prefix) {
        if (!prefix) return;
        fetch(`{{ route('customers.suggest-code') }}?prefix=${encodeURIComponent(prefix)}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            const codeInput = document.getElementById('cust_customer_code');
            if (codeInput && !codeInput.value) {
                codeInput.value = data.next_code || '';
            }
        })
        .catch(() => {});
    }
});
</script>
@endpush
