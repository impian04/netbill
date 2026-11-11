@extends('layouts.app')

@section('title', $customer->name)

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <div>
            <h1 class="page-title">{{ $customer->name }}</h1>
            <span class="font-monospace text-primary fw-semibold small">{{ $customer->customer_code }}</span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-edit-current-customer">
            <i class="bi bi-pencil me-1"></i>Ubah Data
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-delete-current-customer">
            <i class="bi bi-trash me-1"></i>Hapus
        </button>
    </div>
</div>

{{-- ── Kartu Rincian Profil Pelanggan ──────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-person-lines-fill me-2 text-primary"></i>Rincian Profil Pelanggan</span>
        <span class="badge bg-{{ $customer->serviceStatusColor() }}">{{ $customer->service_status }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size:.7rem;">ID Pelanggan</div>
                <div class="fw-bold font-monospace text-primary">{{ $customer->customer_code }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size:.7rem;">Paket Internet</div>
                <div><span class="badge bg-light text-dark border">{{ $customer->package }}</span></div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size:.7rem;">Jenis Tagihan</div>
                <div class="small">{{ $customer->billing_type }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size:.7rem;">Mulai Berlangganan</div>
                <div class="small">{{ tgl_indo($customer->subscribed_at) }}</div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size:.7rem;">Nomor Telepon</div>
                <div class="small">{{ $customer->phone ?? '—' }}</div>
            </div>
            <div class="col-sm-6 col-md-9">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size:.7rem;">Alamat Pemasangan</div>
                <div class="small" style="white-space: pre-line;">{{ $customer->address }}</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Panel Akun & Aktivasi Mobile ────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-phone me-2 text-primary"></i>Aktivasi Akun Aplikasi Mobile</span>
        <span class="badge bg-{{ $customer->activationStatusColor() }}">
            {{ $customer->activationStatusLabel() }}
        </span>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="mb-1 fw-semibold small">
                    @if ($customer->activationStatus() === 'active')
                        <i class="bi bi-check-circle-fill text-success me-1"></i>Pelanggan telah mengaktifkan aplikasi mobile.
                    @elseif ($customer->activationStatus() === 'locked_temporary')
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>Akun terkunci sementara hingga {{ $customer->activation_locked_until?->locale('id')->isoFormat('HH:mm') }}.
                    @elseif ($customer->activationStatus() === 'locked_permanent')
                        <i class="bi bi-x-circle-fill text-danger me-1"></i>Akun terkunci permanen karena salah 100×. Buat kode aktivasi baru untuk membuka.
                    @else
                        <i class="bi bi-info-circle text-muted me-1"></i>Pelanggan belum mengaktifkan aplikasi mobile.
                    @endif
                </p>
                <div class="text-muted small" style="font-size:0.75rem;">
                    Pembuatan kode baru akan mereset kata sandi lama dan mencabut seluruh token sesi pelanggan.
                </div>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-warning fw-semibold px-3" id="btn-generate-activation">
                    <i class="bi bi-key-fill me-1"></i>Buat Kode Baru
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Riwayat Tagihan (DataTables Server-Side) ─────────────────── --}}
<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h5 fw-bold mb-0">Riwayat Tagihan Pelanggan</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('export.invoices', ['search' => $customer->customer_code]) }}" class="btn btn-sm btn-success">
            <i class="bi bi-download me-1"></i>Ekspor CSV
        </a>
        <button type="button" class="btn btn-sm btn-primary" id="btn-add-customer-invoice">
            <i class="bi bi-plus-lg me-1"></i>Tambah Tagihan
        </button>
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
                <button type="button" id="btn-reset-inv-filter" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </button>
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
@include('customers.partials.offcanvas', [
    'packages'     => \App\Models\Customer::select('package')->distinct()->orderBy('package')->pluck('package'),
    'billingTypes' => \App\Models\Customer::select('billing_type')->distinct()->orderBy('billing_type')->pluck('billing_type'),
])

{{-- ── Offcanvas Tagihan (untuk tambah & ubah tagihan) ─────────── --}}
@include('invoices.partials.offcanvas', [
    'customers' => collect([$customer]),
])

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const customerId = {{ $customer->id }};

    // 1. Inisialisasi CRUD Offcanvas Customer (Ubah & Hapus)
    const crudCustomer = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-customer',
        storeUrl: '{{ route('customers.store') }}',
        baseUrl: '{{ url('customers') }}',
        editTitle: 'Ubah Data Pelanggan',
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
        order: [[1, 'desc']], // default period desc
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

    // 3. Inisialisasi CRUD Offcanvas Tagihan
    const crudInvoice = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-invoice',
        storeUrl: '{{ route('invoices.store') }}',
        baseUrl: '{{ url('invoices') }}',
        createTitle: 'Tambah Tagihan Baru',
        editTitle: 'Ubah Data Tagihan',
        table: invoiceTable,
        onOpenCreate: function (form) {
            // Sembunyikan/kunci pilihan customer karena konteksnya sudah di pelanggan ini
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

    // 4. Tombol Buat Kode Aktivasi dengan Konfirmasi Modal Swal (Poin B)
    document.getElementById('btn-generate-activation')?.addEventListener('click', async function () {
        const result = await window.confirmAction({
            title: 'Buat Kode Aktivasi Baru?',
            text: 'Jika pelanggan sudah aktif sebelumnya, akses login aplikasi mobile akan dicabut sampai pelanggan melakukan aktivasi dengan kode baru ini.',
            confirmButtonText: 'Ya, buat kode',
            cancelButtonText: 'Batal',
            confirmButtonClass: 'btn btn-warning',
        });

        if (!result.isConfirmed) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        try {
            const res = await fetch('{{ route('customers.activation.generate', $customer) }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken || '',
                }
            });

            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || 'Gagal membuat kode aktivasi.');
            }

            // Tampilkan modal SweetAlert2 TANPA timer dengan tombol salin (poin B)
            window.showActivationCodeModal(data.code, data.customer_name);
        } catch (err) {
            window.showToast('error', err.message || 'Gagal memproses kode aktivasi.');
        }
    });
});
</script>
@endpush
