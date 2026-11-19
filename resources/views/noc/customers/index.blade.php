@extends('layouts.app')

@section('title', 'Cari Data Pelanggan - NOC')

@section('content')
<x-page-header
    title="Cari Data Pelanggan"
    subtitle="Pencarian cepat informasi operasional pelanggan lintas POP untuk keperluan penanganan NOC."
/>

{{-- Filter Card --}}
<x-filter-card>
    <form id="filterNocCustomersForm" class="row g-2 align-items-end">
    <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold">Filter POP</label>
        <select name="pop_id" id="filter_pop_id" class="form-select form-select-sm">
            <option value="">-- Semua POP --</option>
            @foreach($pops as $pop)
                <option value="{{ $pop->id }}">{{ $pop->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold">Status Layanan</label>
        <select name="service_status" id="filter_service_status" class="form-select form-select-sm">
            <option value="">-- Semua Status --</option>
            <option value="ACTIVE">Aktif (ACTIVE)</option>
            <option value="PENDING">Menunggu (PENDING)</option>
            <option value="SUSPENDED">Isolir (SUSPENDED)</option>
            <option value="TERMINATED">Berhenti (TERMINATED)</option>
        </select>
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold">Pencarian</label>
        <div class="input-group input-group-sm">
            <input type="search" name="q" id="filter_q" class="form-control form-control-sm" placeholder="Nama, kode, telepon (min 3 huruf)...">
            <x-btn type="submit" variant="primary" id="btn-search-noc">Cari</x-btn>
        </div>
    </div>

    <div class="col-12 col-md-2 d-flex align-items-end justify-content-md-end ms-auto">
        <x-btn type="reset" variant="neutral" id="btn-reset-filters">Reset</x-btn>
    </div>
    </form>
</x-filter-card>

{{-- Table Card --}}
<x-table-card title="Data Pelanggan">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 w-100" id="nocCustomersTable">
            <thead class="table-light">
                <tr>
                    <th>Kode</th>
                    <th>Nama Pelanggan</th>
                    <th>POP</th>
                    <th>Telepon</th>
                    <th>Paket</th>
                    <th>Status Layanan</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</x-table-card>

{{-- Offcanvas Detail Pelanggan NOC --}}
<x-offcanvas-form id="nocCustomerDetailOffcanvas" title="Detail Operasional Pelanggan" titleId="nocCustomerDetailTitle" subtitleId="nc_customer_code">
    {{-- Section 1: Data Pokok --}}
        <div class="card mb-3 border-0 bg-light">
            <div class="card-body p-3">
                <div class="row g-2 small">
                    <div class="col-12 col-sm-6">
                        <span class="text-muted d-block">Nama Pelanggan:</span>
                        <strong class="" id="nc_name">-</strong>
                    </div>
                    <div class="col-12 col-sm-6">
                        <span class="text-muted d-block">Nomor Telepon:</span>
                        <div id="nc_phone" class="fw-semibold">-</div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="text-muted d-block">POP:</span>
                        <span id="nc_pop_name" class="fw-semibold">-</span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="text-muted d-block">Paket:</span>
                        <span id="nc_package" class="fw-semibold">-</span>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="text-muted d-block">Status Layanan:</span>
                        <span id="nc_service_status" class="fw-semibold">-</span>
                    </div>
                    <div class="col-12">
                        <span class="text-muted d-block">Alamat Lengkap:</span>
                        <div id="nc_address" class="p-2 bg-white rounded border small mt-1">-</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Jaringan & Lokasi Lapangan --}}
        <div class="card mb-3 border">
            <div class="card-header bg-white py-2 fw-semibold small">
                Informasi Jaringan & Titik Lokasi
            </div>
            <div class="card-body p-3">
                <div class="row g-2 small">
                    <div class="col-6">
                        <span class="text-muted d-block">ODC:</span>
                        <strong id="nc_odc_name">-</strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">ODP:</span>
                        <strong id="nc_odp_name">-</strong>
                    </div>
                    <div class="col-12">
                        <span class="text-muted d-block">Koordinat:</span>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <code id="nc_coordinates">-</code>
                            <x-btn variant="export" href="#" target="_blank" rel="noopener noreferrer"   id="nc_map_link">
                                Rute Maps
                            </x-btn>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 3: Ringkasan 6 Tagihan Terakhir --}}
        <div class="card border">
            <div class="card-header bg-white py-2 fw-semibold small">
                Ringkasan 6 Tagihan Terakhir
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Periode</th>
                                <th>Nominal</th>
                                <th class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody id="nc_invoices_table_body">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-2">Belum ada tagihan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <x-slot:footer>
        <x-btn variant="neutral" data-bs-dismiss="offcanvas">Tutup</x-btn>
    </x-slot:footer>
</x-offcanvas-form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Inisialisasi DataTable Pelanggan NOC Server-Side
    const tableColumns = [
        {
            data: 'customer_code',
            render: function (data, type, row) {
                return `<a href="javascript:void(0)" class="fw-bold text-decoration-none btn-customer-detail" data-id="${row.id}">${window.escapeHtml(data)}</a>`;
            }
        },
        { data: 'name' },
        { data: 'pop_name' },
        { data: 'phone' },
        { data: 'package' },
        {
            data: 'service_status',
            render: function (data) {
                return window.statusBadge('service_status', data);
            }
        },
        {
            data: null,
            orderable: false,
            className: 'text-end',
            render: function (data, type, row) {
                return window.btnIconHtml('view', {
                    class: 'btn-customer-detail',
                    'data-id': row.id,
                    title: 'Detail Pelanggan'
                });
            }
        }
    ];

    const table = window.initServerTable('#nocCustomersTable', {
        ajaxUrl: '{{ route('noc.customers.index') }}',
        filterForm: '#filterNocCustomersForm',
        isRateLimited: true,
        columns: tableColumns,
    });

    // 2. Offcanvas Detail Pelanggan
    const detailOffcanvasEl = document.getElementById('nocCustomerDetailOffcanvas');

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-customer-detail');
        if (!btn) return;

        const id = btn.getAttribute('data-id');

        try {
            const res = await window.apiRequest(`{{ url('customer-lookup') }}/${id}`);
            const cust = res.data || res;
            renderCustomerDetail(cust);
            window.showOffcanvas(detailOffcanvasEl);
        } catch (err) {
            window.toast('error', err.message || 'Gagal memuat detail pelanggan.');
        }
    });

    function renderCustomerDetail(cust) {
        document.getElementById('nc_customer_code').textContent = cust.customer_code;
        document.getElementById('nc_name').textContent = cust.name;
        document.getElementById('nc_phone').textContent = cust.phone || '-';
        document.getElementById('nc_pop_name').textContent = cust.pop?.name || '-';
        document.getElementById('nc_package').textContent = cust.package || '-';
        document.getElementById('nc_service_status').textContent = cust.service_status;
        document.getElementById('nc_address').textContent = cust.address || '-';

        const badgeHtml = window.statusBadge('service_status', cust.service_status);
        const tmp = document.createElement('div');
        tmp.innerHTML = badgeHtml;
        const badgeEl = tmp.firstElementChild;
        const stBadge = document.getElementById('nc_status_badge');
        stBadge.className = badgeEl.className;
        stBadge.textContent = badgeEl.textContent;

        document.getElementById('nc_odc_name').textContent = cust.odc?.name || '-';
        document.getElementById('nc_odp_name').textContent = cust.odp?.name || '-';

        const coordEl = document.getElementById('nc_coordinates');
        const mapLink = document.getElementById('nc_map_link');
        if (cust.latitude && cust.longitude) {
            coordEl.textContent = `${cust.latitude}, ${cust.longitude}`;
            mapLink.href = `https://www.google.com/maps/dir/?api=1&destination=${cust.latitude},${cust.longitude}`;
            mapLink.classList.remove('d-none');
        } else {
            coordEl.textContent = 'Belum ditentukan';
            mapLink.classList.add('d-none');
        }

        // 6 Tagihan Terakhir
        const tbody = document.getElementById('nc_invoices_table_body');
        tbody.innerHTML = '';
        if (!cust.recent_invoices || cust.recent_invoices.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2">Belum ada catatan tagihan.</td></tr>';
        } else {
            cust.recent_invoices.forEach(inv => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${window.escapeHtml(inv.period)}</strong></td>
                    <td>${window.formatRupiah(inv.amount)}</td>
                    <td class="text-end">${window.statusBadge('invoice_status', inv.status)}</td>
                `;
                tbody.appendChild(tr);
            });
        }
    }
});
</script>
@endpush
@endsection
