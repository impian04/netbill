@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

@php
    $chartLabels   = $periodSummary->map(fn ($p) => bulan_indo($p->period))->values();
    $chartPaid     = $periodSummary->pluck('paid_count')->values();
    $chartUnpaid   = $periodSummary->pluck('unpaid_count')->values();

    $totalInvoices = $stats->total_invoices ?? 0;
    $paidCount     = $stats->paid_count ?? 0;
    $unpaidCount   = $stats->unpaid_count ?? 0;
    $paidAmount    = $stats->paid_amount ?? 0;
    $unpaidAmount  = $stats->unpaid_amount ?? 0;
    $paidPct       = $totalInvoices > 0 ? round($paidCount / $totalInvoices * 100) : 0;
@endphp

{{-- ── Page header ─────────────────────────────────────────────── --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Ringkasan status tagihan dan performa operasional NetBill</p>
    </div>
    <!-- <div class="d-flex gap-2">
        <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-people me-1"></i>Pelanggan
        </a>
        <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-receipt me-1"></i>Tagihan
        </a>
    </div> -->
</div>

{{-- ── Filter bar ───────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-body py-2">
        <form id="dashboard-filter-form" method="GET" action="{{ route('dashboard') }}">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-sm-auto">
                    <label class="form-label small fw-semibold text-muted mb-0 me-2">Periode:</label>
                </div>
                <div class="col-6 col-sm-auto">
                    <input type="month" name="period" id="dash-period" class="form-control form-control-sm"
                           value="{{ $filters['period'] ?? '' }}">
                </div>
                <div class="col-6 col-sm-auto" style="min-width:130px;">
                    <select name="year" id="dash-year" class="form-select form-select-sm">
                        <option value="">Semua Tahun</option>
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected(($filters['year'] ?? '') == $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-auto d-flex gap-2 ms-sm-auto">
                    <button type="submit" id="btn-dash-filter" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-funnel me-1"></i>Terapkan
                    </button>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── Stat cards (grid row g-3: col-12 col-sm-6 col-xl-3, h-100) ── --}}
<div class="row g-3 mb-4">
    {{-- Total Pelanggan --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card" style="border-left:3px solid #2563eb;">
            <div class="stat-icon" style="background:#dbeafe;">
                <i class="bi bi-people-fill text-primary"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value">{{ number_format($totalCustomers) }}</div>
                <div class="stat-label">Total Pelanggan</div>
                <div class="stat-help">Seluruh pelanggan terdaftar di sistem</div>
            </div>
        </div>
    </div>

    {{-- Total Tagihan --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card" style="border-left:3px solid #9333ea;" >
            <div class="stat-icon" style="background:#f3e8ff;">
                <i class="bi bi-receipt" style="color:#9333ea;"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value">{{ number_format($totalInvoices) }}</div>
                <div class="stat-label">Total Tagihan</div>
                <div class="stat-help">Jumlah invoice pada filter aktif</div>
            </div>
        </div>
    </div>

    {{-- Tagihan Lunas --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card" style="border-left:3px solid #16a34a;">
            <div class="stat-icon" style="background:#dcfce7;">
                <i class="bi bi-check-circle-fill text-success"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value text-success">{{ number_format($paidCount) }}</div>
                <div class="stat-label">Tagihan Lunas</div>
                <div class="stat-help">
                    <span class="text-success fw-semibold">{{ rupiah($paidAmount) }}</span> ditangguhkan
                </div>
            </div>
        </div>
    </div>

    {{-- Tagihan Belum Bayar --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card" style="border-left:3px solid #dc2626;">
            <div class="stat-icon" style="background:#fee2e2;">
                <i class="bi bi-clock-fill text-danger"></i>
            </div>
            <div class="flex-grow-1">
                <div class="stat-value text-danger">{{ number_format($unpaidCount) }}</div>
                <div class="stat-label">Tagihan Belum Bayar</div>
                <div class="stat-help">
                    <span class="text-danger fw-semibold">{{ rupiah($unpaidAmount) }}</span> tertunggak
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Grafik (col-lg-8) & Perlu Perhatian (col-lg-4) berdampingan ── --}}
<div class="row g-3 mb-4">
    {{-- Sisi Kiri: Grafik Statistik (col-lg-8) --}}
    <div class="col-12 col-lg-8">
        <div class="row g-3">
            {{-- Ringkasan per Periode --}}
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-bar-chart me-2 text-primary"></i>Tren Tagihan per Periode</span>
                        <span class="text-muted small">{{ $periodSummary->count() > 0 ? $periodSummary->count() . ' periode terakhir' : '—' }}</span>
                    </div>
                    <div class="card-body py-3">
                        @if ($periodSummary->isNotEmpty())
                            <canvas id="period-bar" height="170"></canvas>
                            <p class="text-muted text-center mt-2 mb-0" style="font-size:.72rem;">
                                Perbandingan tagihan lunas (hijau) vs belum bayar (merah) per bulan.
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

            {{-- Distribusi Status Donut --}}
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-pie-chart me-2 text-primary"></i>Rasio Pelunasan Tagihan</span>
                        <span class="badge bg-light text-dark border">{{ $paidPct }}% Lunas</span>
                    </div>
                    <div class="card-body d-flex flex-column flex-sm-row align-items-center justify-content-around py-3">
                        @if ($totalInvoices > 0)
                            <div style="position:relative;width:150px;height:150px;">
                                <canvas id="status-doughnut" width="150" height="150"></canvas>
                                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                                    <span class="fw-bold fs-4 text-dark">{{ $paidPct }}%</span>
                                    <span class="text-muted" style="font-size:.7rem;">Lunas</span>
                                </div>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span style="width:12px;height:12px;background:#16a34a;border-radius:3px;display:inline-block;"></span>
                                    <span class="small text-muted">Lunas: <strong>{{ number_format($paidCount) }}</strong> invoice</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span style="width:12px;height:12px;background:#dc2626;border-radius:3px;display:inline-block;"></span>
                                    <span class="small text-muted">Belum Bayar: <strong>{{ number_format($unpaidCount) }}</strong> invoice</span>
                                </div>
                                <div class="text-muted small" style="font-size:0.75rem;">
                                    Total nominal tertunggak: <span class="text-danger fw-semibold">{{ rupiah($unpaidAmount) }}</span>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-pie-chart fs-1 d-block mb-1 opacity-25"></i>
                                <span class="small">Tidak ada data invoice</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sisi Kanan: Daftar "Perlu Perhatian" (col-lg-4) --}}
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Perlu Perhatian</span>
                <span class="badge bg-danger text-white" style="font-size:.7rem;">Tunggakan Terlama</span>
            </div>
            <div class="card-body p-0">
                @if ($oldestUnpaid->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-check-circle fs-1 d-block mb-2 text-success opacity-50"></i>
                        <p class="fw-semibold mb-0 small">Semua tagihan sudah lunas!</p>
                    </div>
                @else
                    <div class="list-group list-group-flush" style="max-height: 480px; overflow-y: auto;">
                        @foreach ($oldestUnpaid as $inv)
                            @php
                                $monthsDue = $inv->period->diffInMonths(now()->startOfMonth());
                            @endphp
                            <div class="list-group-item list-group-item-action p-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <a href="{{ route('customers.show', $inv->customer) }}" class="fw-semibold text-decoration-none text-dark small">
                                        {{ $inv->customer->name }}
                                    </a>
                                    @if ($monthsDue >= 3)
                                        <span class="badge badge-unpaid">{{ $monthsDue }} bln</span>
                                    @elseif ($monthsDue >= 1)
                                        <span class="badge" style="background:#fef9c3;color:#92400e;">{{ $monthsDue }} bln</span>
                                    @else
                                        <span class="badge bg-light text-muted border">Bulan ini</span>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center justify-content-between text-muted" style="font-size:0.75rem;">
                                    <span class="font-monospace text-primary">{{ $inv->customer->customer_code }}</span>
                                    <span class="fw-semibold text-danger">{{ rupiah($inv->deferred_amount) }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mt-1 text-muted" style="font-size:0.72rem;">
                                    <span>Periode: {{ bulan_indo($inv->period) }}</span>
                                    <a href="{{ route('customers.show', $inv->customer) }}" class="text-decoration-none">
                                        Rincian <i class="bi bi-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="card-footer bg-white border-top py-2">
                <span class="text-muted" style="font-size:.72rem;">
                    <i class="bi bi-info-circle me-1"></i>Diurutkan berdasarkan tanggal tagihan terlama yang belum lunas.
                </span>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
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
                    borderWidth: 0,
                    hoverOffset: 4,
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
                    },
                    {
                        label: 'Belum Bayar',
                        data: @json($chartUnpaid),
                        backgroundColor: '#dc2626',
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, pointStyleWidth: 8, font: { size: 11 } }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0, font: { size: 10 } },
                        grid: { color: '#f1f5f9' }
                    }
                }
            }
        });
    }

    // ── Filter form submit ──
    var filterForm = document.getElementById('dashboard-filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function () {
            var btn = document.getElementById('btn-dash-filter');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memuat...';
            btn.disabled = true;
        });
    }
});
</script>
@endpush
