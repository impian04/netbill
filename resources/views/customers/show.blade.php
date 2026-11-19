@extends('layouts.app')

@section('title', $customer->name)

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <x-btn variant="neutral" href="{{ route('customers.index') }}">
            Kembali
        </x-btn>
        <div>
            <h1 class="page-title">{{ $customer->name }}</h1>
            <span class="font-monospace text-primary fw-semibold small">{{ $customer->customer_code }}</span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <x-btn variant="neutral" id="btn-edit-current-customer">
            Ubah Data
        </x-btn>
        <x-btn variant="danger" id="btn-delete-current-customer">
            Hapus
        </x-btn>
    </div>
</div>

{{-- ── Kartu Rincian Profil Pelanggan ──────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Rincian Profil Pelanggan</span>
        <x-status-badge type="service_status" :status="$customer->service_status" />
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">ID Pelanggan</div>
                <div class="fw-bold font-monospace text-primary">{{ $customer->customer_code }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Paket Internet</div>
                <div><span class="badge bg-light text-dark border">{{ $customer->package }}</span></div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Jenis Tagihan</div>
                <div class="small">{{ $customer->billing_type }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Mulai Berlangganan</div>
                <div class="small">{{ tgl_indo($customer->subscribed_at) }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Nomor Telepon</div>
                <div class="small">{{ $customer->phone ?? '—' }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">ODC</div>
                <div class="small fw-semibold">{{ $customer->odc?->name ?? '—' }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">ODP</div>
                <div class="small fw-semibold">{{ $customer->odp?->name ?? '—' }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Koordinat Lokasi</div>
                <div class="small">
                    @if ($customer->latitude !== null && $customer->longitude !== null)
                        <span class="font-monospace">{{ $customer->latitude }}, {{ $customer->longitude }}</span>
                        <a href="https://www.google.com/maps?q={{ $customer->latitude }},{{ $customer->longitude }}"
                           target="_blank" rel="noopener noreferrer" class="d-inline-block text-primary small ms-1" title="Buka di Google Maps">
                        </a>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Berkas KTP</div>
                <div class="small">
                    @if ($customer->ktp_path)
                        <x-btn variant="export" href="{{ route('files.ktp.customer', $customer) }}" target="_blank" rel="noopener noreferrer">
                            Lihat KTP
                        </x-btn>
                    @else
                        <span class="text-muted small">Belum diunggah</span>
                    @endif
                </div>
            </div>
            <div class="col-sm-6 col-md-6">
                <div class="text-muted small fw-semibold text-uppercase">Alamat Pemasangan</div>
                <div class="small" style="white-space: pre-line;">{{ $customer->address }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase">Asal Pendaftaran</div>
                <div class="small"><span class="badge bg-light text-dark border">{{ strtoupper($customer->registered_via ?? 'admin') }}</span></div>
            </div>
        </div>
    </div>
</div>

{{-- ── Panel Riwayat Pemasangan & Aktivasi ──────────────────────── --}}
<div class="card mb-4">
    <div class="card-header">
        Riwayat Pemasangan &amp; Aktivasi
    </div>
    <div class="card-body p-3">
        <div class="row g-3">
            {{-- 1. Pendaftaran --}}
            <div class="col-md-3">
                <div class="border rounded p-2 h-100 bg-light">
                    <div class="d-flex align-items-center mb-1">
                        <strong class="small text-dark">1. Pendaftaran</strong>
                    </div>
                    <div class="small text-muted mb-1">
                        Asal: <span class="badge bg-light text-dark border">{{ strtoupper($customer->registered_via ?? 'admin') }}</span>
                    </div>
                    <div class="small text-muted mb-1">
                        Oleh: <strong class="text-dark">{{ $customer->referredBy ? 'Marketing (' . $customer->referredBy->name . ')' : 'Admin' }}</strong>
                    </div>
                    <div class="small text-muted">
                        Tanggal: {{ $customer->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>
            </div>

            {{-- 2. Survei --}}
            <div class="col-md-3">
                <div class="border rounded p-2 h-100 bg-light">
                    <div class="d-flex align-items-center mb-1">
                        <strong class="small text-dark">2. Survei Lapangan</strong>
                    </div>
                    @if($customer->survey)
                        <div class="small mb-1">
                            Hasil: <x-status-badge type="survey_result" :status="$customer->survey->result->value" />
                        </div>
                        <div class="small text-muted mb-1">
                            Surveyor: <strong class="text-dark">{{ $customer->survey->surveyor?->name ?? '-' }}</strong>
                        </div>
                        <div class="small text-muted mb-1">
                            ODC/ODP: {{ $customer->survey->odc?->name ?? '-' }} / {{ $customer->survey->odp?->name ?? '-' }}
                        </div>
                        <div class="small text-muted">
                            Tanggal: {{ $customer->survey->created_at->format('d/m/Y H:i') }}
                        </div>
                    @else
                        <div class="small text-muted py-2">Belum ada survei lapangan.</div>
                    @endif
                </div>
            </div>

            {{-- 3. Penugasan Teknisi --}}
            <div class="col-md-3">
                <div class="border rounded p-2 h-100 bg-light">
                    <div class="d-flex align-items-center mb-1">
                        <strong class="small text-dark">3. Penugasan Teknisi</strong>
                    </div>
                    @if($customer->workOrder)
                        <div class="small mb-1">
                            Status: <x-status-badge type="work_order_status" :status="$customer->workOrder->status->value" />
                        </div>
                        <div class="small text-muted mb-1">
                            Teknisi: <strong class="text-dark">{{ $customer->workOrder->technician?->name ?? '-' }}</strong>
                        </div>
                        <div class="small text-muted mb-1">
                            Rencana: {{ $customer->workOrder->scheduled_date?->format('d/m/Y') ?? '-' }}
                        </div>
                        <div class="small text-muted">
                            Selesai: {{ $customer->workOrder->completed_on?->format('d/m/Y') ?? '-' }}
                        </div>
                    @else
                        <div class="small text-muted py-2">Belum ditugaskan ke teknisi.</div>
                    @endif
                </div>
            </div>

            {{-- 4. Verifikasi & Aktivasi --}}
            <div class="col-md-3">
                <div class="border rounded p-2 h-100 bg-light">
                    <div class="d-flex align-items-center mb-1">
                        <strong class="small text-dark">4. Verifikasi &amp; Aktivasi</strong>
                    </div>
                    @if($customer->workOrder && $customer->workOrder->verified_at)
                        <div class="small mb-1">
                            Status: <x-status-badge type="active_status" status="ACTIVE" />
                        </div>
                        <div class="small text-muted mb-1">
                            Verifikator: <strong class="text-dark">{{ $customer->workOrder->verifier?->name ?? '-' }}</strong>
                        </div>
                        <div class="small text-muted mb-1">
                            Tanggal Aktif: <strong class="text-dark">{{ $customer->workOrder->activated_on?->format('d/m/Y') ?? '-' }}</strong>
                        </div>
                        <div class="small text-muted">
                            Waktu Verif: {{ $customer->workOrder->verified_at->format('d/m/Y H:i') }}
                        </div>
                    @elseif($customer->service_status === 'ACTIVE')
                        <div class="small mb-1">
                            Status: <x-status-badge type="service_status" status="ACTIVE" />
                        </div>
                        <div class="small text-muted">
                            Mulai Berlangganan: {{ tgl_indo($customer->subscribed_at) }}
                        </div>
                    @else
                        <div class="small text-muted py-2">Belum diverifikasi / belum aktif.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Panel Akun & Aktivasi Mobile ────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Aktivasi Akun Aplikasi Mobile</span>
        <x-status-badge type="active_status" :status="$customer->activationStatus() === 'active' ? 'ACTIVE' : 'INACTIVE'" />
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="mb-1 fw-semibold small">
                    @if ($customer->activationStatus() === 'active')
                        Pelanggan telah mengaktifkan aplikasi mobile.
                    @elseif ($customer->activationStatus() === 'locked_temporary')
                        Akun terkunci sementara hingga {{ $customer->activation_locked_until?->locale('id')->isoFormat('HH:mm') }}.
                    @elseif ($customer->activationStatus() === 'locked_permanent')
                        Akun terkunci permanen karena salah 100×. Buat kode aktivasi baru untuk membuka.
                    @else
                        Pelanggan belum mengaktifkan aplikasi mobile.
                    @endif
                </p>
                <div class="text-muted small">
                    Pembuatan kode baru akan mereset kata sandi lama dan mencabut seluruh token sesi pelanggan.
                </div>
            </div>
            <div>
                <x-btn variant="warning" id="btn-generate-activation">
                    Buat Kode Baru
                </x-btn>
            </div>
        </div>
    </div>
</div>

{{-- ── Panel Riwayat Tiket Komplain (NOC & Admin) ───────────────── --}}
@if(auth()->user()->isSuperAdmin() || auth()->user()->isAdmin() || auth()->user()->isNoc())
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>Riwayat Tiket Komplain (5 Terakhir)</span>
        <a href="{{ route('tickets.index', ['search' => $customer->customer_code]) }}" class="small text-decoration-none">
            Lihat Semua Tiket &rarr;
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 140px;">Nomor Tiket</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        <th>Prioritas</th>
                        <th>Tanggal Buka</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentTickets ?? [] as $tkt)
                        <tr>
                            <td>
                                <strong class="text-primary">{{ $tkt->ticket_number }}</strong>
                                <div class="text-muted text-truncate" style="max-width: 200px;">{{ $tkt->subject }}</div>
                            </td>
                            <td><x-status-badge type="ticket_category" :status="$tkt->category->value" /></td>
                            <td><x-status-badge type="ticket_status" :status="$tkt->status->value" /></td>
                            <td><x-status-badge type="ticket_priority" :status="$tkt->priority->value" /></td>
                            <td>{{ $tkt->opened_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td class="text-end">
                                <x-btn-icon action="view" href="{{ route('tickets.index') }}" title="Buka di Menu Tiket" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Belum ada riwayat tiket untuk pelanggan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ── Riwayat Tagihan (DataTables Server-Side) ─────────────────── --}}
<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h5 fw-bold mb-0">Riwayat Tagihan Pelanggan</h2>
    <div class="d-flex gap-2">
        <x-btn variant="export" href="{{ route('export.invoices', ['search' => $customer->customer_code]) }}">
            Ekspor CSV
        </x-btn>
        <x-btn variant="primary" id="btn-add-customer-invoice">
            Tambah Tagihan
        </x-btn>
    </div>
</div>

{{-- Filter Tagihan Pelanggan --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form id="filter-customer-invoices-form" class="row g-2 align-items-center">
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">

            <div class="col-6 col-md-3">
                <select name="status" id="filter-inv-status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="PAID">Lunas</option>
                    <option value="UNPAID">Belum Bayar</option>
                </select>
            </div>

            <div class="col-6 col-md-3">
                <input type="month" name="period" id="filter-inv-period" class="form-control form-control-sm"
                       placeholder="Filter Periode">
            </div>

            <div class="col-6 col-md-3">
                <select name="year" id="filter-inv-year" class="form-select form-select-sm">
                    <option value="">Semua Tahun</option>
                    @foreach ($years as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-3 text-end">
                <x-btn variant="neutral" id="btn-reset-inv-filter" class="w-100">
                    Reset
                </x-btn>
            </div>
        </form>
    </div>
</div>

{{-- Tabel DataTables Tagihan Pelanggan --}}
<div class="card">
    <div class="card-body p-3">
        <table id="customer-invoices-table" class="table table-hover align-middle mb-0 w-100">
            <thead>
                <tr>
                    <th>No. Tagihan</th>
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

{{-- ── Offcanvas Pelanggan (untuk ubah data) ───────────────────── --}}
@include('customers.partials.offcanvas')

{{-- ── Offcanvas Tagihan (untuk tambah & ubah tagihan) ─────────── --}}
@include('invoices.partials.offcanvas', [
    'customers' => collect([$customer]),
])

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const customerId = {{ $customer->id }};

    // Cascading dropdown ODC & ODP di Offcanvas
    const odcSelect = document.getElementById('cust_odc_id');
    const odpSelect = document.getElementById('cust_odp_id');

    function filterOffcanvasOdp(odcId, selectedOdpId = null) {
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
            const optOdc = opt.getAttribute('data-odc-id');
            if (optOdc === String(odcId)) {
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
            filterOffcanvasOdp(this.value);
        });
    }

    // 1. Inisialisasi CRUD Offcanvas Customer (Ubah & Hapus)
    const crudCustomer = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-customer',
        storeUrl: '{{ route('customers.store') }}',
        baseUrl: '{{ url('customers') }}',
        editTitle: 'Ubah Data Pelanggan',
        onOpenEdit: function (form, record) {
            if (odcSelect && record.odc_id) {
                odcSelect.value = record.odc_id;
            }
            filterOffcanvasOdp(record.odc_id, record.odp_id);

            const ktpWrapper = document.getElementById('cust_ktp_preview_wrapper');
            const ktpLink = document.getElementById('cust_ktp_view_link');
            const deleteKtpCb = document.getElementById('cust_delete_ktp');
            const ktpFileInput = document.getElementById('cust_ktp_file');

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
        },
        onSuccess: function () {
            window.location.reload();
        }
    });

    document.getElementById('btn-edit-current-customer')?.addEventListener('click', function () {
        crudCustomer.openEdit(customerId, '{{ route('customers.show', $customer) }}');
    });

    document.getElementById('btn-delete-current-customer')?.addEventListener('click', function () {
        crudCustomer.deleteItem(
            '{{ route('customers.destroy', $customer) }}',
            'Hapus pelanggan "{{ $customer->name }}" beserta seluruh riwayat tagihannya?'
        );
    });

    // 2. Inisialisasi DataTable Tagihan Pelanggan
    const invoiceTable = window.initServerTable('#customer-invoices-table', {
        ajaxUrl: '{{ route('invoices.index') }}',
        filterForm: '#filter-customer-invoices-form',
        resetButton: '#btn-reset-inv-filter',
        pageLength: 10,
        order: [[1, 'desc']],
        columns: [
            {
                data: 'invoice_number',
                name: 'invoice_number',
                render: function (data) {
                    return `<span class="font-monospace small text-primary fw-semibold">${window.escapeHtml(data || '—')}</span>`;
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
                    return window.statusBadge('invoice_status', data);
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
                        <div class="d-flex justify-content-end gap-1">
                            ${window.btnIconHtml('edit', { class: 'btn-edit-invoice', 'data-id': row.id, 'data-url': row.urls.show, title: 'Ubah Tagihan' })}
                            ${window.btnIconHtml('delete', { class: 'btn-delete-invoice', 'data-url': row.urls.delete, 'data-period': window.escapeHtml(row.period), title: 'Hapus Tagihan' })}
                        </div>
                    `;
                }
            }
        ]
    });

    // 3. Inisialisasi CRUD Offcanvas Tagihan
    const crudInvoice = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-invoice',
        storeUrl: '{{ route('invoices.store') }}',
        baseUrl: '{{ url('invoices') }}',
        createTitle: 'Tambah Tagihan Baru',
        editTitle: 'Ubah Data Tagihan',
        table: invoiceTable,
        onOpenCreate: function (form) {
            const customerSelect = form.elements['customer_id'];
            if (customerSelect) {
                customerSelect.value = customerId;
                document.getElementById('invoice-customer-field').style.display = 'none';
            }
        },
        onOpenEdit: function (form) {
            document.getElementById('invoice-customer-field').style.display = 'none';
        }
    });

    // Buka Tambah Tagihan
    document.getElementById('btn-add-customer-invoice')?.addEventListener('click', function () {
        crudInvoice.openCreate({ customer_id: customerId });
    });

    // Edit Tagihan
    $('#customer-invoices-table').on('click', '.btn-edit-invoice', function () {
        const id = $(this).data('id');
        const url = $(this).data('url');
        crudInvoice.openEdit(id, url);
    });

    // Hapus Tagihan
    $('#customer-invoices-table').on('click', '.btn-delete-invoice', function () {
        const url = $(this).data('url');
        const period = $(this).data('period');
        crudInvoice.deleteItem(url, `Hapus tagihan periode ${period}?`);
    });

    // 4. Tombol Buat Kode Aktivasi dengan Konfirmasi Modal Swal
    document.getElementById('btn-generate-activation')?.addEventListener('click', async function () {
        const result = await window.confirmAction({
            title: 'Buat Kode Aktivasi Baru?',
            text: 'Jika pelanggan sudah aktif sebelumnya, akses login aplikasi mobile akan dicabut sampai pelanggan melakukan aktivasi dengan kode baru ini.',
            confirmButtonText: 'Ya, buat kode',
            cancelButtonText: 'Batal',
            confirmButtonClass: 'btn btn-warning',
        });

        if (!result.isConfirmed) return;

        try {
            const data = await window.apiRequest('{{ route('customers.activation.generate', $customer) }}', {
                method: 'POST'
            });

            window.showActivationCodeModal(data.code, data.customer_name);
        } catch (err) {
            window.toast('error', err.message || 'Gagal memproses kode aktivasi.');
        }
    });
});
</script>
@endpush
