@extends('layouts.app')

@section('title', $customer->name)

@section('content')

{{-- Header --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ $customer->name }}</h1>
            <code class="text-primary small">{{ $customer->customer_code }}</code>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-customer"
                data-name="{{ $customer->name }}"
                data-action="{{ route('customers.destroy', $customer) }}">
            <i class="bi bi-trash me-1"></i>Hapus
        </button>
    </div>
</div>

{{-- Customer info card --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6 col-md-3">
                <p class="text-muted small mb-1 fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.5px;">Kode Pelanggan</p>
                <p class="mb-0 fw-bold font-monospace">{{ $customer->customer_code }}</p>
            </div>
            <div class="col-sm-6 col-md-3">
                <p class="text-muted small mb-1 fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.5px;">Paket</p>
                <p class="mb-0">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-semibold">
                        {{ $customer->package }}
                    </span>
                </p>
            </div>
            <div class="col-sm-6 col-md-3">
                <p class="text-muted small mb-1 fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.5px;">Status Layanan</p>
                <p class="mb-0">
                    <span class="badge bg-{{ $customer->serviceStatusColor() }}">{{ $customer->service_status }}</span>
                </p>
            </div>
            <div class="col-sm-6 col-md-3">
                <p class="text-muted small mb-1 fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.5px;">Mulai Berlangganan</p>
                <p class="mb-0">{{ tgl_indo($customer->subscribed_at) }}</p>
            </div>
            <div class="col-sm-6 col-md-3">
                <p class="text-muted small mb-1 fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.5px;">No. Telepon</p>
                <p class="mb-0 font-monospace small">{{ $customer->phone ?? '—' }}</p>
            </div>
            <div class="col-sm-6 col-md-3">
                <p class="text-muted small mb-1 fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.5px;">Jenis Tagihan</p>
                <p class="mb-0">{{ $customer->billing_type }}</p>
            </div>
            <div class="col-sm-6 col-md-6">
                <p class="text-muted small mb-1 fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.5px;">Alamat</p>
                <p class="mb-0">{{ $customer->address }}</p>
            </div>
        </div>
    </div>
</div>

{{-- Activation panel --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-shield-lock me-2"></i>Akun Aplikasi Mobile</span>
        <span class="badge bg-{{ $customer->activationStatusColor() }}">
            {{ $customer->activationStatusLabel() }}
        </span>
    </div>
    <div class="card-body">

        {{-- Show activation code ONCE --}}
        @if ($activationCode)
            <div class="alert alert-warning d-flex gap-3 align-items-start mb-3">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1 fs-5"></i>
                <div>
                    <div class="fw-bold mb-1">Kode Aktivasi (tampil sekali saja):</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="font-monospace fs-4 fw-bold letter-spacing-2" id="activation-code-display">
                            {{ $activationCode }}
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-copy-code"
                                onclick="copyCode()">
                            <i class="bi bi-clipboard"></i> Salin
                        </button>
                    </div>
                    <div class="text-muted small mt-1">
                        Berikan kode ini ke pelanggan. Kode berlaku 7 hari.
                        Setelah halaman ini ditutup/direfresh, kode tidak bisa ditampilkan lagi.
                    </div>
                </div>
            </div>
        @endif

        <div class="d-flex align-items-center gap-3">
            <form method="POST" action="{{ route('customers.activation.generate', $customer) }}"
                  id="form-gen-code" onsubmit="return confirmGenerate()">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm" id="btn-gen-code">
                    <i class="bi bi-key me-1"></i>Buat Kode Aktivasi
                </button>
            </form>
            <span class="text-muted small">
                @if ($customer->activationStatus() === 'active')
                    Membuat kode baru akan <strong>mencabut akses login</strong> pelanggan.
                @else
                    Pelanggan belum bisa login ke aplikasi mobile.
                @endif
            </span>
        </div>
    </div>
</div>


{{-- Invoices section --}}
<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h5 fw-bold mb-0">Riwayat Tagihan</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('export.invoices', array_merge(array_filter($filters), ['search' => $customer->customer_code])) }}"
           id="btn-export-invoices"
           class="btn btn-sm btn-outline-success d-flex align-items-center gap-1"
           title="Export tagihan pelanggan ini">
            <i class="bi bi-download"></i>Export CSV
        </a>
        <a href="{{ route('customers.invoices.create', $customer) }}"
           id="btn-tambah-tagihan" class="btn btn-sm btn-primary d-flex align-items-center gap-1">
            <i class="bi bi-plus-lg"></i>Tambah Tagihan
        </a>
    </div>
</div>

{{-- Invoice filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form id="invoice-filter-form" method="GET" action="{{ route('customers.show', $customer) }}">
            <div class="row g-2 align-items-end">
                <div class="col-sm-3" style="min-width:130px;">
                    <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                    <select name="status" id="inv-select-status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="PAID" @selected(($filters['status'] ?? '') === 'PAID')>Lunas</option>
                        <option value="UNPAID" @selected(($filters['status'] ?? '') === 'UNPAID')>Belum Bayar</option>
                    </select>
                </div>
                <div class="col-sm-3" style="min-width:140px;">
                    <label class="form-label small fw-semibold text-muted mb-1">Periode</label>
                    <input type="month" name="period" id="inv-input-period" class="form-control form-control-sm"
                           value="{{ $filters['period'] ?? '' }}">
                </div>
                <div class="col-sm-2" style="min-width:110px;">
                    <label class="form-label small fw-semibold text-muted mb-1">Tahun</label>
                    <select name="year" id="inv-select-year" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected(($filters['year'] ?? '') == $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" id="btn-filter-inv" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                </div>
            </div>
            @if (!empty($filters['sort']))
                <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            @endif
            @if (!empty($filters['direction']))
                <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
            @endif
        </form>
    </div>
</div>

{{-- Invoices results info --}}
<div class="mb-2 px-1">
    <span class="text-muted small">
        @if ($invoices->total() > 0)
            Menampilkan <strong>{{ $invoices->firstItem() }}–{{ $invoices->lastItem() }}</strong>
            dari <strong>{{ $invoices->total() }}</strong> tagihan
        @else
            Tidak ada tagihan yang cocok.
        @endif
    </span>
</div>

{{-- Invoices table --}}
<div class="card">
    <div class="card-body p-0">
        @if ($invoices->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-receipt fs-1 d-block mb-2 opacity-25"></i>
                <p class="fw-semibold mb-1">Belum ada tagihan</p>
                @if (array_filter(array_diff_key($filters, ['sort' => 1, 'direction' => 1])))
                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-outline-secondary mt-1">Reset Filter</a>
                @else
                    <a href="{{ route('customers.invoices.create', $customer) }}" class="btn btn-sm btn-primary mt-1">
                        <i class="bi bi-plus-lg me-1"></i>Tambah Tagihan Pertama
                    </a>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="invoices-table">
                    <thead>
                        <tr>
                            <th>@include('layouts.partials.sort-th', ['col' => 'period', 'label' => 'Periode', 'filters' => $filters, 'routeName' => 'customers.show', 'routeParams' => ['customer' => $customer->id]])</th>
                            <th>@include('layouts.partials.sort-th', ['col' => 'deferred_amount', 'label' => 'Nominal Ditangguhkan', 'filters' => $filters, 'routeName' => 'customers.show', 'routeParams' => ['customer' => $customer->id]])</th>
                            <th>@include('layouts.partials.sort-th', ['col' => 'status', 'label' => 'Status', 'filters' => $filters, 'routeName' => 'customers.show', 'routeParams' => ['customer' => $customer->id]])</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="fw-semibold">{{ bulan_indo($invoice->period) }}</td>
                                <td>{{ rupiah($invoice->deferred_amount) }}</td>
                                <td>
                                    @if ($invoice->status === 'PAID')
                                        <span class="badge badge-paid px-2 py-1">
                                            <i class="bi bi-check-circle me-1"></i>Lunas
                                        </span>
                                    @else
                                        <span class="badge badge-unpaid px-2 py-1">
                                            <i class="bi bi-clock me-1"></i>Belum Bayar
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('invoices.edit', $invoice) }}"
                                           class="btn btn-sm btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger btn-delete-invoice"
                                                data-name="tagihan {{ bulan_indo($invoice->period) }}"
                                                data-action="{{ route('invoices.destroy', $invoice) }}"
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

            @if ($invoices->hasPages())
                <div class="d-flex justify-content-center py-3 border-top">
                    {{ $invoices->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
</div>

{{-- Delete modal (shared) --}}
@include('layouts.partials.confirm-delete-modal')

@endsection

@push('scripts')
<script>
// Activation: copy code to clipboard
function copyCode() {
    var code = document.getElementById('activation-code-display');
    if (code) {
        navigator.clipboard.writeText(code.textContent.trim()).then(function () {
            var btn = document.getElementById('btn-copy-code');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Tersalin!';
            btn.classList.add('btn-success'); btn.classList.remove('btn-outline-secondary');
            setTimeout(function () {
                btn.innerHTML = '<i class="bi bi-clipboard"></i> Salin';
                btn.classList.remove('btn-success'); btn.classList.add('btn-outline-secondary');
            }, 2000);
        });
    }
}

// Activation: confirm before generate
function confirmGenerate() {
    return confirm('Buat kode aktivasi baru?\n\nJika pelanggan sudah aktif, akses login mereka akan dicabut sampai mereka menggunakan kode baru ini.');
}

// Wire delete buttons (customer + invoices)
document.querySelectorAll('.btn-delete-customer, .btn-delete-invoice').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('delete-modal-name').textContent = this.dataset.name;
        document.getElementById('delete-form').action = this.dataset.action;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});

// Spinner on invoice filter submit
document.getElementById('invoice-filter-form').addEventListener('submit', function () {
    var btn = document.getElementById('btn-filter-inv');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memfilter...';
    btn.disabled = true;
});
</script>
@endpush
