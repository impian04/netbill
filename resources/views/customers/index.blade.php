@extends('layouts.app')

@section('title', 'Pelanggan')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Daftar Pelanggan" subtitle="Kelola data pelanggan internet, paket layanan, dan aktivasi akun">
    <x-slot:actions>
        <x-btn variant="export" href="{{ route('export.customers') }}" id="btn-export-customers">
            Ekspor XLSX
        </x-btn>
        <x-btn variant="primary" id="btn-open-create-customer">
            Tambah Pelanggan
        </x-btn>
    </x-slot:actions>
</x-page-header>

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<x-filter-card>
    <form id="filter-customers-form" class="row g-2 align-items-center">
        <div class="col-12 {{ auth()->user()->isSuperAdmin() ? 'col-lg-3' : 'col-lg-4' }}">
            <input type="search" id="filter-search" name="search" class="form-control form-control-sm"
                   placeholder="Cari kode, nama, atau alamat (otomatis)...">
        </div>

        @if(auth()->user()->isSuperAdmin())
            <div class="col-6 col-lg-2">
                <select name="pop_id" id="filter-pop-id" class="form-select form-select-sm">
                    <option value="">Semua POP</option>
                    @foreach ($pops as $pop)
                        <option value="{{ $pop->id }}">POP {{ $pop->code }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="col-6 {{ auth()->user()->isSuperAdmin() ? 'col-lg-2' : 'col-lg-2' }}">
            <select name="service_status" id="filter-service-status" class="form-select form-select-sm">
                <option value="">Semua Status Layanan</option>
                @foreach (\App\Models\Customer::SERVICE_STATUSES as $ss)
                    <option value="{{ $ss }}">{{ $ss }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 {{ auth()->user()->isSuperAdmin() ? 'col-lg-2' : 'col-lg-2' }}">
            <select name="package" id="filter-package" class="form-select form-select-sm">
                <option value="">Semua Paket</option>
                @foreach ($packages as $pkg)
                    <option value="{{ $pkg }}">{{ $pkg }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 {{ auth()->user()->isSuperAdmin() ? 'col-lg-2' : 'col-lg-2' }}">
            <select name="billing_type" id="filter-billing-type" class="form-select form-select-sm">
                <option value="">Semua Jenis Tagihan</option>
                @foreach ($billingTypes as $bt)
                    <option value="{{ $bt }}">{{ $bt }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-lg-2">
            <select name="odc_id" id="filter-odc" class="form-select form-select-sm">
                <option value="">Semua ODC</option>
                @foreach ($odcs as $odc)
                    <option value="{{ $odc->id }}" data-pop-id="{{ $odc->pop_id }}">{{ $odc->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-lg-2">
            <select name="odp_id" id="filter-odp" class="form-select form-select-sm">
                <option value="">Semua ODP</option>
                @foreach ($odps as $odp)
                    <option value="{{ $odp->id }}" data-pop-id="{{ $odp->pop_id }}" data-odc-id="{{ $odp->odc_id }}">{{ $odp->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 {{ auth()->user()->isSuperAdmin() ? 'col-lg-1' : 'col-lg-2' }} text-end ms-lg-auto">
            <x-btn variant="neutral" id="btn-reset-filter" class="w-100">
                Reset
            </x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
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
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</x-table-card>

{{-- ── Offcanvas Form (Tambah & Ubah) ───────────────────────────── --}}
@include('customers.partials.offcanvas')

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
                    return `<span class="badge bg-secondary">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'billing_type',
                name: 'billing_type',
                className: 'd-none d-lg-table-cell small',
                render: function (data, type, row) {
                    let unpaidBadge = '';
                    if (row.unpaid_count > 0) {
                        unpaidBadge = `<span class="badge bg-danger ms-1" title="${row.unpaid_count} tagihan belum bayar">${row.unpaid_count} unpaid</span>`;
                    }
                    return `${window.escapeHtml(data)} ${unpaidBadge}`;
                }
            },
            {
                data: 'service_status',
                name: 'service_status',
                render: function (data) {
                    return window.statusBadge('service_status', data);
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
                            ${window.btnIconHtml('view', { href: row.urls.show, title: 'Lihat Detail' })}
                            ${window.btnIconHtml('edit', { 'data-id': row.id, 'data-url': row.urls.show, title: 'Ubah Data', class: 'btn-edit-customer' })}
                            ${window.btnIconHtml('delete', { 'data-url': row.urls.delete, 'data-name': row.name, title: 'Hapus Pelanggan', class: 'btn-delete-customer' })}
                        </div>
                    `;
                }
            }
        ]
    });

    const coordInput   = document.getElementById('cust_coordinates');
    const mapBtn       = document.getElementById('btn-open-map-link');
    const getLocBtn    = document.getElementById('btn-get-current-loc');
    const ktpWrapper   = document.getElementById('cust_ktp_preview_wrapper');
    const ktpLink      = document.getElementById('cust_ktp_view_link');
    const deleteKtpCb  = document.getElementById('cust_delete_ktp');
    const ktpFileInput = document.getElementById('cust_ktp_file');
    const statusSelect = document.getElementById('cust_service_status');

    function updateMapLink() {
        if (!coordInput || !mapBtn) return;
        const val = coordInput.value.trim();
        if (val && val.includes(',')) {
            mapBtn.href = `https://www.google.com/maps?q=${encodeURIComponent(val)}`;
            mapBtn.classList.remove('d-none');
        } else {
            mapBtn.classList.add('d-none');
        }
    }

    if (coordInput) {
        coordInput.addEventListener('input', updateMapLink);
    }

    if (getLocBtn) {
        // Geolocation only works in secure contexts (HTTPS or localhost)
        if (!window.isSecureContext || !navigator.geolocation) {
            getLocBtn.disabled = true;
            getLocBtn.title = 'Geolocation memerlukan koneksi aman (HTTPS / localhost)';
        } else {
            getLocBtn.addEventListener('click', function () {
                getLocBtn.disabled = true;
                getLocBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mendeteksi...';

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        getLocBtn.disabled = false;
                        getLocBtn.innerHTML = 'Gunakan Lokasi Saya';
                        const lat = pos.coords.latitude.toFixed(7);
                        const lng = pos.coords.longitude.toFixed(7);
                        coordInput.value = `${lat}, ${lng}`;
                        updateMapLink();
                        window.showToast('success', 'Lokasi berhasil dideteksi.');
                    },
                    function (err) {
                        getLocBtn.disabled = false;
                        getLocBtn.innerHTML = 'Gunakan Lokasi Saya';
                        window.showToast('error', 'Gagal mendeteksi lokasi: ' + (err.message || 'Izin ditolak.'));
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            });
        }
    }

    // Cascading dropdown ODC & ODP di Offcanvas
    const odcSelect = document.getElementById('cust_odc_id');
    const odpSelect = document.getElementById('cust_odp_id');

    function filterOffcanvasOdcOdp(popId, selectedOdcId = null, selectedOdpId = null) {
        if (!odcSelect || !odpSelect) return;

        const odcOptions = odcSelect.querySelectorAll('option');
        odcOptions.forEach(opt => {
            if (!opt.value) return;
            const optPop = opt.getAttribute('data-pop-id');
            if (!popId || optPop === String(popId)) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });

        if (selectedOdcId) {
            odcSelect.value = selectedOdcId;
        } else {
            const currentOdcOpt = odcSelect.selectedOptions[0];
            if (currentOdcOpt && currentOdcOpt.style.display === 'none') {
                odcSelect.value = '';
            }
        }

        filterOffcanvasOdp(odcSelect.value, popId, selectedOdpId);
    }

    function filterOffcanvasOdp(odcId, popId, selectedOdpId = null) {
        if (!odpSelect) return;

        if (!odcId) {
            odpSelect.value = '';
            odpSelect.disabled = true;
            return;
        }

        odpSelect.disabled = false;
        const odpOptions = odpSelect.querySelectorAll('option');
        odpOptions.forEach(opt => {
            if (!opt.value) return;
            const optPop = opt.getAttribute('data-pop-id');
            const optOdc = opt.getAttribute('data-odc-id');
            const matchesPop = !popId || optPop === String(popId);
            const matchesOdc = optOdc === String(odcId);
            if (matchesPop && matchesOdc) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });

        if (selectedOdpId) {
            odpSelect.value = selectedOdpId;
        } else {
            const currentOdpOpt = odpSelect.selectedOptions[0];
            if (currentOdpOpt && currentOdpOpt.style.display === 'none') {
                odpSelect.value = '';
            }
        }
    }

    if (odcSelect) {
        odcSelect.addEventListener('change', function () {
            const popSelect = document.getElementById('cust_pop_id');
            const popId = popSelect ? popSelect.value : '';
            filterOffcanvasOdp(this.value, popId);
        });
    }

    // Filter ODC & ODP pada Filter Card (Tabel)
    const filterPopEl = document.getElementById('filter-pop-id');
    const filterOdcEl = document.getElementById('filter-odc');
    const filterOdpEl = document.getElementById('filter-odp');

    if (filterPopEl) {
        filterPopEl.addEventListener('change', function () {
            const popId = this.value;
            if (filterOdcEl) {
                filterOdcEl.querySelectorAll('option').forEach(opt => {
                    if (!opt.value) return;
                    opt.style.display = (!popId || opt.getAttribute('data-pop-id') === popId) ? '' : 'none';
                });
                filterOdcEl.value = '';
            }
            if (filterOdpEl) {
                filterOdpEl.querySelectorAll('option').forEach(opt => {
                    if (!opt.value) return;
                    opt.style.display = (!popId || opt.getAttribute('data-pop-id') === popId) ? '' : 'none';
                });
                filterOdpEl.value = '';
            }
        });
    }

    if (filterOdcEl) {
        filterOdcEl.addEventListener('change', function () {
            const odcId = this.value;
            const popId = filterPopEl ? filterPopEl.value : '';
            if (filterOdpEl) {
                filterOdpEl.querySelectorAll('option').forEach(opt => {
                    if (!opt.value) return;
                    const matchesPop = !popId || opt.getAttribute('data-pop-id') === popId;
                    const matchesOdc = !odcId || opt.getAttribute('data-odc-id') === odcId;
                    opt.style.display = (matchesPop && matchesOdc) ? '' : 'none';
                });
                filterOdpEl.value = '';
            }
        });
    }

    // 2. Inisialisasi CRUD Offcanvas Pelanggan
    const crudCustomer = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-customer',
        storeUrl: '{{ route('customers.store') }}',
        baseUrl: '{{ url('customers') }}',
        createTitle: 'Tambah Pelanggan',
        editTitle: 'Ubah Data Pelanggan',
        table: table,
        onOpenCreate: function (form) {
            const popSelect = document.getElementById('cust_pop_id');
            const popId = popSelect ? popSelect.value : '';
            fetchNextCode(popId);
            filterOffcanvasOdcOdp(popId, null, null);

            if (statusSelect) statusSelect.value = 'PENDING';
            if (ktpWrapper) ktpWrapper.classList.add('d-none');
            if (ktpLink) ktpLink.href = '#';
            if (deleteKtpCb) deleteKtpCb.checked = false;
            if (ktpFileInput) ktpFileInput.value = '';
            if (coordInput) coordInput.value = '';
            updateMapLink();
        },
        onOpenEdit: function (form, record) {
            const popSelect = document.getElementById('cust_pop_id');
            const popId = record.pop_id || (popSelect ? popSelect.value : '');
            filterOffcanvasOdcOdp(popId, record.odc_id, record.odp_id);

            if (record.has_ktp && record.ktp_url) {
                if (ktpWrapper) {
                    ktpWrapper.classList.remove('d-none');
                    ktpWrapper.classList.add('d-flex');
                }
                if (ktpLink) ktpLink.href = record.ktp_url;
            } else {
                if (ktpWrapper) ktpWrapper.classList.add('d-none');
            }
            if (deleteKtpCb) deleteKtpCb.checked = false;
            if (ktpFileInput) ktpFileInput.value = '';
            updateMapLink();
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

    // Helper saran ID otomatis saat ganti POP
    const popSelectEl = document.getElementById('cust_pop_id');
    if (popSelectEl && popSelectEl.tagName === 'SELECT') {
        popSelectEl.addEventListener('change', function () {
            fetchNextCode(this.value);
            filterOffcanvasOdcOdp(this.value, null, null);
        });
    }

    function fetchNextCode(popId) {
        const url = popId
            ? `{{ route('customers.suggest-code') }}?pop_id=${encodeURIComponent(popId)}`
            : `{{ route('customers.suggest-code') }}`;

        window.apiRequest(url)
            .then(data => {
                const codeInput = document.getElementById('cust_customer_code');
                if (codeInput) {
                    codeInput.value = data.next_code || '';
                }
            })
            .catch(() => {});
    }
});
</script>
@endpush
