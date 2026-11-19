@extends('layouts.app')

@section('title', 'Status Import')

@section('content')

{{-- Auto-refresh when still running --}}
@if ($batch->isRunning())
    <meta http-equiv="refresh" content="4">
@endif

<div class="d-flex align-items-center gap-3 mb-4">
    <x-btn variant="neutral" href="{{ route('imports.index') }}"  >
        Kembali
    </x-btn>
    <div>
        <h1 class="h4 fw-bold mb-0">Status Import</h1>
        <p class="text-muted small mb-0 font-monospace">{{ $batch->original_name }}</p>
    </div>
    <span class="badge bg-{{ $batch->statusColor() }} ms-1">
        @if ($batch->isRunning())
            <span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem;"></span>
        @endif
        {{ $batch->statusLabel() }}
    </span>
</div>

{{-- Progress + summary --}}
<div class="row g-3 mb-4">

    {{-- Progress card --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Progres</div>
            <div class="card-body">

                @if ($batch->total_rows)
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Diproses</span>
                        <span class="fw-semibold">
                            {{ number_format($batch->processed_rows) }} /
                            {{ number_format($batch->total_rows) }} baris
                        </span>
                    </div>
                    <div class="progress mb-3" style="height:10px;">
                        <div class="progress-bar {{ $batch->status === 'failed' ? 'bg-danger' : 'bg-primary' }}"
                             role="progressbar"
                             style="width:{{ $batch->progressPercent() }}%"
                             aria-valuenow="{{ $batch->progressPercent() }}"
                             aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                @elseif ($batch->status === 'queued')
                    <p class="text-muted small mb-3">
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        Menunggu worker queue…
                    </p>
                @endif

                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted ps-0">File</td>
                        <td class="fw-medium">{{ $batch->original_name }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Kebijakan Duplikat</td>
                        <td>
                            @if ($batch->duplicate_policy === 'skip')
                                <span class="badge bg-secondary">Lewati</span>
                            @else
                                <span class="badge bg-info">Perbarui</span>
                            @endif
                        </td>
                    </tr>
                    @if ($batch->started_at)
                        <tr>
                            <td class="text-muted ps-0">Mulai</td>
                            <td>{{ tgl_indo($batch->started_at) }} {{ $batch->started_at->format('H:i:s') }}</td>
                        </tr>
                    @endif
                    @if ($batch->finished_at)
                        <tr>
                            <td class="text-muted ps-0">Selesai</td>
                            <td>{{ tgl_indo($batch->finished_at) }} {{ $batch->finished_at->format('H:i:s') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Durasi</td>
                            <td>{{ $batch->started_at->diffForHumans($batch->finished_at, true) }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Results counters --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Ringkasan Hasil</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded text-center" style="background:#dcfce7;">
                            <div class="fw-bold" style="color:#166534;">
                                {{ number_format($batch->created_count) }}
                            </div>
                            <div class="small text-success">Data Baru Dibuat</div>
                            <div class="text-muted">Pelanggan + tagihan baru</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded text-center" style="background:#dbeafe;">
                            <div class="fw-bold" style="color:#1d4ed8;">
                                {{ number_format($batch->updated_count) }}
                            </div>
                            <div class="small text-primary">Diperbarui</div>
                            <div class="text-muted">Duplikat yang ditimpa</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded text-center" style="background:#f1f5f9;">
                            <div class="fw-bold" style="color:#475569;">
                                {{ number_format($batch->skipped_count) }}
                            </div>
                            <div class="small text-secondary">Dilewati</div>
                            <div class="text-muted">Duplikat yang dibiarkan</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded text-center" style="background:{{ $batch->error_count > 0 ? '#fee2e2' : '#f1f5f9' }};">
                            <div class="fw-bold" style="color:{{ $batch->error_count > 0 ? '#991b1b' : '#475569' }};">
                                {{ number_format($batch->error_count) }}
                            </div>
                            <div class="small" style="color:{{ $batch->error_count > 0 ? '#dc2626' : '#64748b' }};">Error</div>
                            <div class="text-muted">Baris gagal diproses</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Error details --}}
@if (!empty($batch->errors))
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span>
                Detail Error ({{ count($batch->errors) }} baris)
            </span>
            <span class="text-muted small">Baris lain tetap diproses</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0" id="errors-table">
                    <thead class="sticky-top">
                        <tr>
                            <th style="width:80px;">Baris</th>
                            <th>Pesan Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batch->errors as $err)
                            <tr>
                                <td>
                                    <span class="badge badge-unpaid">Baris {{ $err['row'] ?? '?' }}</span>
                                </td>
                                <td class="small">
                                    @foreach ((array) ($err['messages'] ?? [$err['error'] ?? '?']) as $msg)
                                        <div>{{ $msg }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@elseif ($batch->status === 'done' && $batch->error_count === 0)
    <div class="alert alert-success d-flex gap-2">
        <span>Import selesai tanpa error. Semua baris berhasil diproses.</span>
    </div>
@endif

@endsection
