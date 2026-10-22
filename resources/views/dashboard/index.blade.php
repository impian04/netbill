@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

@php
    // Persiapkan data chart di Blade agar bisa di-JSON-encode
    $chartLabels  = $periodSummary->map(fn ($p) => bulan_indo($p->period))->values();
    $chartPaid    = $periodSummary->pluck('paid_count')->values();
    $chartUnpaid  = $periodSummary->pluck('unpaid_count')->values();

    $totalInvoices   = $stats->total_invoices ?? 0;
    $paidCount       = $stats->paid_count ?? 0;
    $unpaidCount     = $stats->unpaid_count ?? 0;
    $paidAmount      = $stats->paid_amount ?? 0;
    $unpaidAmount    = $stats->unpaid_amount ?? 0;
    $paidPct         = $totalInvoices > 0 ? round($paidCount / $totalInvoices * 100) : 0;
@endphp

{{-- ── Page header ─────────────────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Dashboard</h1>
        <p class="text-muted small mb-0">Ringkasan data tagihan NetBill</p>
    </div>
    <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-people me-1"></i>Lihat Pelanggan
    </a>
</div>

{{-- ── Filter bar ───────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-body py-2">
        <form id="dashboard-filter-form" method="GET" action="{{ route('dashboard') }}">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small fw-semibold text-muted mb-1">Periode</label>
                    <input type="month" name="period" id="dash-period" class="form-control form-control-sm"
                           value="{{ $filters['period'] ?? '' }}">
                </div>
                <div class="col-auto" style="min-width:110px;">
                    <label class="form-label small fw-semibold text-muted mb-1">Tahun</label>
                    <select name="year" id="dash-year" class="form-select form-select-sm">
                        <option value="">Semua Tahun</option>
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected(($filters['year'] ?? '') == $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" id="btn-dash-filter" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-funnel me-1"></i>Terapkan
                    </button>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                </div>
                @if (!empty(array_filter($filters)))
                    <div class="col-auto">
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-funnel-fill me-1"></i>Filter aktif
                        </span>
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- ── Stat cards ───────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">

    {{-- Total Pelanggan --}}
    <div class="col-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="stat-icon" style="background:#dbeafe;">
                <i class="bi bi-people-fill" style="color:#2563eb;"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value">{{ number_format($totalCustomers) }}</div>
                <div class="stat-label">Total Pelanggan</div>
                <div class="text-muted" style="font-size:.7rem;margin-top:.2rem;" title="Jumlah pelanggan terdaftar di sistem">
                    <i class="bi bi-info-circle me-1"></i>Seluruh pelanggan aktif
                </div>
            </div>
        </div>
    </div>

    {{-- Total Invoice --}}
    <div class="col-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="stat-icon" style="background:#f1f5f9;">
                <i class="bi bi-receipt" style="color:#475569;"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value">{{ number_format($totalInvoices) }}</div>
                <div class="stat-label">Total Tagihan</div>
                <div class="text-muted" style="font-size:.7rem;margin-top:.2rem;">
                    <i class="bi bi-info-circle me-1"></i>Semua tagihan pada filter ini
                </div>
            </div>
        </div>
    </div>

    {{-- Lunas --}}
    <div class="col-6 col-xl-3">
        <div class="stat-card h-100" style="border-left:3px solid #16a34a;">
            <div class="stat-icon" style="background:#dcfce7;">
                <i class="bi bi-check-circle-fill" style="color:#16a34a;"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value text-success">{{ number_format($paidCount) }}</div>
                <div class="stat-label">Tagihan Lunas</div>
                <div class="text-muted" style="font-size:.7rem;margin-top:.2rem;">
                    <span class="text-success fw-semibold">{{ rupiah($paidAmount) }}</span>
                    ditangguhkan
                </div>
                <div class="text-muted" style="font-size:.68rem;">
                    <i class="bi bi-info-circle me-1"></i>Nominal ditangguhkan ≠ pendapatan
                </div>
            </div>
        </div>
    </div>

    {{-- Belum Bayar --}}
    <div class="col-6 col-xl-3">
        <div class="stat-card h-100" style="border-left:3px solid #dc2626;">
            <div class="stat-icon" style="background:#fee2e2;">
                <i class="bi bi-clock-fill" style="color:#dc2626;"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value text-danger">{{ number_format($unpaidCount) }}</div>
                <div class="stat-label">Tagihan Belum Bayar</div>
                <div class="text-muted" style="font-size:.7rem;margin-top:.2rem;">
                    <span class="text-danger fw-semibold">{{ rupiah($unpaidAmount) }}</span>
                    tertunggak
                </div>
                <div class="text-muted" style="font-size:.68rem;">
                    <i class="bi bi-info-circle me-1"></i>Total nominal yang belum dilunasi
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ── Charts row ───────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">

    {{-- Distribusi Status (Doughnut) --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-pie-chart me-2"></i>Distribusi Status</span>
                <span class="badge bg-light text-dark border fw-normal" style="font-size:.72rem;">
                    {{ $paidPct }}% lunas
                </span>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-3">
                @if ($totalInvoices > 0)
                    <div style="position:relative;width:200px;height:200px;">
                        <canvas id="status-doughnut" width="200" height="200"></canvas>
                        <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                            <span class="fw-bold" style="font-size:1.4rem;color:#0f172a;">{{ $paidPct }}%</span>
                            <span class="text-muted" style="font-size:.72rem;">Lunas</span>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mt-3">
                        <div class="d-flex align-items-center gap-1">
                            <span style="width:12px;height:12px;background:#16a34a;border-radius:3px;display:inline-block;"></span>
                            <span class="small text-muted">Lunas ({{ $paidCount }})</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span style="width:12px;height:12px;background:#dc2626;border-radius:3px;display:inline-block;"></span>
                            <span class="small text-muted">Belum Bayar ({{ $unpaidCount }})</span>
                        </div>
                    </div>
                    <p class="text-muted text-center mt-2 mb-0" style="font-size:.72rem;">
                        Proporsi tagihan lunas vs belum bayar dari total {{ number_format($totalInvoices) }} tagihan
                        pada filter yang dipilih.
                    </p>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-pie-chart fs-1 d-block mb-2 opacity-25"></i>
                        <p class="small mb-0">Tidak ada data</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Ringkasan per Periode (Bar chart) --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-bar-chart me-2"></i>Ringkasan per Periode</span>
                <span class="text-muted small">{{ $periodSummary->count() > 0 ? $periodSummary->count() . ' periode' : '—' }}</span>
            </div>
            <div class="card-body py-3">
                @if ($periodSummary->isNotEmpty())
                    <canvas id="period-bar" height="200"></canvas>
                    <p class="text-muted text-center mt-2 mb-0" style="font-size:.72rem;">
                        Jumlah tagihan lunas vs belum bayar per periode (maks. 12 periode terbaru).
                        Periode tanpa tagihan tidak ditampilkan.
                    </p>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-25"></i>
                        <p class="small mb-0">Tidak ada data periode</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- ── Tagihan UNPAID Terlama ────────────────────────────────────── --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Tagihan Menunggak Terlama</span>
        <span class="text-muted small">10 teratas</span>
    </div>
    <div class="card-body p-0">
        @if ($oldestUnpaid->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-check-circle fs-1 d-block mb-2 text-success opacity-50"></i>
                <p class="fw-semibold mb-0">Semua tagihan sudah lunas!</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="oldest-unpaid-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Pelanggan</th>
                            <th>Paket</th>
                            <th>Periode</th>
                            <th>Nominal Ditangguhkan</th>
                            <th>Lama Tunggakan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($oldestUnpaid as $i => $invoice)
                            @php
                                $monthsDue = $invoice->period->diffInMonths(now()->startOfMonth());
                            @endphp
                            <tr>
                                <td class="text-muted small">{{ $i + 1 }}</td>
                                <td>
                                    <a href="{{ route('customers.show', $invoice->customer) }}"
                                       class="fw-semibold text-decoration-none text-dark">
                                        {{ $invoice->customer->name }}
                                    </a>
                                    <div class="font-monospace text-primary" style="font-size:.75rem;">
                                        {{ $invoice->customer->customer_code }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-normal small">
                                        {{ $invoice->customer->package }}
                                    </span>
                                </td>
                                <td class="fw-semibold">{{ bulan_indo($invoice->period) }}</td>
                                <td>{{ rupiah($invoice->deferred_amount) }}</td>
                                <td>
                                    @if ($monthsDue >= 3)
                                        <span class="badge badge-unpaid">{{ $monthsDue }} bln</span>
                                    @elseif ($monthsDue >= 1)
                                        <span class="badge" style="background:#fef9c3;color:#92400e;">{{ $monthsDue }} bln</span>
                                    @else
                                        <span class="badge bg-light text-muted border">Bulan ini</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('invoices.edit', $invoice) }}"
                                       class="btn btn-sm btn-outline-success" title="Ubah status">
                                        <i class="bi bi-check2-circle me-1"></i>Tandai Lunas
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top py-2">
                <p class="text-muted mb-0" style="font-size:.72rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Lama Tunggakan</strong> dihitung dari periode tagihan hingga bulan ini.
                    <strong>Nominal Ditangguhkan</strong> adalah tagihan yang belum dibayar — bukan pendapatan yang diterima.
                    Klik <em>Tandai Lunas</em> untuk memperbarui status.
                </p>
            </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    // ── Doughnut: Distribusi Status ──
    var doughnutEl = document.getElementById('status-doughnut');
    if (doughnutEl && typeof Chart !== 'undefined') {
        new Chart(doughnutEl, {
            type: 'doughnut',
            data: {
                labels: ['Lunas', 'Belum Bayar'],
                datasets: [{
                    data: [{{ $paidCount }}, {{ $unpaidCount }}],
                    backgroundColor: ['#16a34a', '#dc2626'],
                    hoverBackgroundColor: ['#15803d', '#b91c1c'],
                    borderWidth: 0,
                    hoverOffset: 6,
                }],
            },
            options: {
                cutout: '72%',
                responsive: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                                return ' ' + ctx.label + ': ' + ctx.formattedValue + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // ── Bar: Ringkasan per Periode ──
    var barEl = document.getElementById('period-bar');
    if (barEl && typeof Chart !== 'undefined') {
        new Chart(barEl, {
            type: 'bar',
            data: {
                labels: @json($chartLabels),
                datasets: [
                    {
                        label: 'Lunas',
                        data: @json($chartPaid),
                        backgroundColor: '#16a34a',
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'Belum Bayar',
                        data: @json($chartUnpaid),
                        backgroundColor: '#dc2626',
                        borderRadius: 4,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, pointStyleWidth: 10, font: { size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            footer: function (items) {
                                var total = items.reduce(function (a, b) { return a + b.parsed.y; }, 0);
                                return 'Total: ' + total;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0, font: { size: 11 } },
                        grid: { color: '#f1f5f9' }
                    }
                }
            }
        });
    }

    // ── Filter spinner ──
    var filterForm = document.getElementById('dashboard-filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function () {
            var btn = document.getElementById('btn-dash-filter');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memuat...';
            btn.disabled = true;
        });
    }
}());
</script>
@endpush
