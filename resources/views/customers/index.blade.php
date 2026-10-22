@extends('layouts.app')

@section('title', 'Pelanggan')

@section('content')

{{-- Page header --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Daftar Pelanggan</h1>
        <p class="text-muted small mb-0">Kelola data pelanggan internet</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('export.customers', array_filter($filters)) }}"
           id="btn-export-customers"
           class="btn btn-sm btn-outline-success d-flex align-items-center gap-1"
           title="Export data sesuai filter aktif">
            <i class="bi bi-download"></i>Export CSV
        </a>
        <a href="{{ route('customers.create') }}" id="btn-tambah-pelanggan"
           class="btn btn-primary d-flex align-items-center gap-2">
            <i class="bi bi-plus-lg"></i>Tambah Pelanggan
        </a>
    </div>
</div>

{{-- Filter card --}}
<div class="card mb-3">
    <div class="card-body">
        <form id="filter-form" method="GET" action="{{ route('customers.index') }}">
            <div class="row g-2 align-items-end">

                {{-- Search --}}
                <div class="col-12 col-lg-4">
                    <label class="form-label small fw-semibold text-muted mb-1">Cari</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted small"></i>
                        </span>
                        <input type="text" name="search" id="input-search"
                               class="form-control border-start-0 ps-0"
                               placeholder="Kode, nama, atau alamat..."
                               value="{{ $filters['search'] ?? '' }}">
                    </div>
                </div>

                {{-- Paket --}}
                <div class="col-6 col-sm-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Paket</label>
                    <select name="package" id="select-package" class="form-select form-select-sm">
                        <option value="">Semua Paket</option>
                        @foreach ($packages as $pkg)
                            <option value="{{ $pkg }}" @selected(($filters['package'] ?? '') === $pkg)>{{ $pkg }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Jenis Tagihan --}}
                <div class="col-6 col-sm-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Jenis Tagihan</label>
                    <select name="billing_type" id="select-billing-type" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach ($billingTypes as $bt)
                            <option value="{{ $bt }}" @selected(($filters['billing_type'] ?? '') === $bt)>{{ $bt }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status Tagihan --}}
                <div class="col-6 col-sm-4 col-lg-1" style="min-width:120px;">
                    <label class="form-label small fw-semibold text-muted mb-1">Tagihan</label>
                    <select name="status" id="select-status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="PAID" @selected(($filters['status'] ?? '') === 'PAID')>Lunas</option>
                        <option value="UNPAID" @selected(($filters['status'] ?? '') === 'UNPAID')>Belum Bayar</option>
                    </select>
                </div>

                {{-- Status Layanan --}}
                <div class="col-6 col-sm-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Status Layanan</label>
                    <select name="service_status" id="select-service-status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="ACTIVE" @selected(($filters['service_status'] ?? '') === 'ACTIVE')>ACTIVE</option>
                        <option value="PENDING" @selected(($filters['service_status'] ?? '') === 'PENDING')>PENDING</option>
                        <option value="SUSPENDED" @selected(($filters['service_status'] ?? '') === 'SUSPENDED')>SUSPENDED</option>
                        <option value="INACTIVE" @selected(($filters['service_status'] ?? '') === 'INACTIVE')>INACTIVE</option>
                    </select>
                </div>

                {{-- Tahun --}}
                <div class="col-6 col-sm-4 col-lg-1" style="min-width:105px;">
                    <label class="form-label small fw-semibold text-muted mb-1">Tahun</label>
                    <select name="year" id="select-year" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected(($filters['year'] ?? '') == $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Periode --}}
                <div class="col-6 col-sm-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Periode</label>
                    <input type="month" name="period" id="input-period" class="form-control form-control-sm"
                           value="{{ $filters['period'] ?? '' }}">
                </div>

                {{-- Buttons --}}
                <div class="col-12 d-flex gap-2 pt-1">
                    <button type="submit" id="btn-filter" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('customers.index') }}" id="btn-reset-filter"
                       class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                </div>
            </div>

            {{-- Preserve sort/direction on filter submit --}}
            @if (!empty($filters['sort']))
                <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            @endif
            @if (!empty($filters['direction']))
                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
            @endif
        </form>
    </div>
</div>

{{-- Results info --}}
<div class="mb-2 px-1">
    <span class="text-muted small">
        @if ($customers->total() > 0)
            Menampilkan <strong>{{ $customers->firstItem() }}–{{ $customers->lastItem() }}</strong>
            dari <strong>{{ number_format($customers->total()) }}</strong> pelanggan
        @else
            <span class="text-muted">Tidak ada pelanggan yang cocok.</span>
        @endif
    </span>
</div>

{{-- Table card --}}
<div class="card" id="customers-table-card">
    <div class="card-body p-0">

        @if ($customers->isEmpty())
            {{-- Empty state --}}
            <div class="text-center py-5 text-muted" id="empty-state">
                <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
                <p class="fw-semibold mb-1">Tidak ada pelanggan ditemukan</p>
                <p class="small mb-3">Coba ubah filter atau tambah pelanggan baru.</p>
                @if (array_filter(array_diff_key($filters, ['sort' => 1, 'direction' => 1])))
                    <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Reset Filter
                    </a>
                @endif
            </div>

        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="customers-table">
                    <thead>
                        <tr>
                            <th>@include('layouts.partials.sort-th', ['col' => 'customer_code', 'label' => 'Kode', 'filters' => $filters, 'routeName' => 'customers.index'])</th>
                            <th>@include('layouts.partials.sort-th', ['col' => 'name', 'label' => 'Nama / Telepon', 'filters' => $filters, 'routeName' => 'customers.index'])</th>
                            <th>@include('layouts.partials.sort-th', ['col' => 'package', 'label' => 'Paket', 'filters' => $filters, 'routeName' => 'customers.index'])</th>
                            <th class="text-center">Status Layanan</th>
                            <th>Jenis Tagihan</th>
                            <th>@include('layouts.partials.sort-th', ['col' => 'subscribed_at', 'label' => 'Mulai Langganan', 'filters' => $filters, 'routeName' => 'customers.index'])</th>
                            <th class="text-center">Invoice</th>
                            <th class="text-center">Belum Bayar</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td>
                                    <a href="{{ route('customers.show', $customer) }}"
                                       class="fw-semibold text-primary text-decoration-none font-monospace small">
                                        {{ $customer->customer_code }}
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-medium">{{ $customer->name }}</div>
                                    @if ($customer->phone)
                                        <div class="text-muted font-monospace small" style="font-size: .75rem;">
                                            <i class="bi bi-telephone me-1"></i>{{ $customer->phone }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-normal">
                                        {{ $customer->package }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $customer->serviceStatusColor() }}">
                                        {{ $customer->service_status }}
                                    </span>
                                </td>
                                <td class="text-muted small">{{ $customer->billing_type }}</td>
                                <td class="text-muted small">{{ tgl_indo($customer->subscribed_at) }}</td>
                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill">{{ $customer->invoices_count }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($customer->unpaid_count > 0)
                                        <span class="badge badge-unpaid rounded-pill fw-semibold">
                                            {{ $customer->unpaid_count }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('customers.show', $customer) }}"
                                           class="btn btn-sm btn-outline-primary" title="Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('customers.edit', $customer) }}"
                                           class="btn btn-sm btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger btn-delete"
                                                data-name="{{ $customer->name }}"
                                                data-action="{{ route('customers.destroy', $customer) }}"
                                                title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($customers->hasPages())
                <div class="d-flex justify-content-center py-3 border-top">
                    {{ $customers->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
</div>

{{-- Delete modal --}}
@include('layouts.partials.confirm-delete-modal')

@endsection

@push('scripts')
<script>
// Wire up delete modal
document.querySelectorAll('.btn-delete').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('delete-modal-name').textContent = this.dataset.name;
        document.getElementById('delete-form').action = this.dataset.action;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});

// Show spinner on filter submit
document.getElementById('filter-form').addEventListener('submit', function () {
    var btn = document.getElementById('btn-filter');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Memfilter...';
    btn.disabled = true;
});
</script>
@endpush
