@extends('layouts.app')

@section('title', 'Catatan Aktivitas')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Catatan Aktivitas Sistem" subtitle="Audit trail rekam jejak operasional, perubahan data, hak akses, dan transaksi sistem" />

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<x-filter-card>
    <form id="filter-logs-form" class="row g-2 align-items-center">
        <div class="col-12 col-lg-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"></span>
                <input type="search" id="filter-log-search" name="search" class="form-control form-control-sm"
                       placeholder="Cari keterangan, aksi, IP...">
            </div>
        </div>

        <div class="col-6 col-lg-2">
            <select name="action" id="filter-log-action" class="form-select form-select-sm">
                <option value="">Semua Aksi</option>
                @foreach($actions as $act)
                    <option value="{{ $act }}">{{ $act }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-lg-2">
            <select name="user_id" id="filter-log-user" class="form-select form-select-sm">
                <option value="">Semua Pengguna</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->label() }})</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-lg-2">
            <select name="pop_id" id="filter-log-pop" class="form-select form-select-sm">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}">{{ $pop->code }} - {{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-lg-3 text-end">
            <x-btn variant="neutral" id="btn-reset-log-filter" class="w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
    <table id="activity-logs-table" class="table table-hover align-middle mb-0 w-100">
        <thead>
            <tr>
                <th style="width: 150px;">Waktu</th>
                <th style="width: 150px;">Pengguna</th>
                <th style="width: 100px;">Peran</th>
                <th style="width: 90px;">POP</th>
                <th style="width: 130px;">Aksi</th>
                <th>Keterangan Aktivitas</th>
                <th class="font-monospace text-muted" style="width: 110px;">IP Address</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</x-table-card>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.initServerTable('#activity-logs-table', {
        ajaxUrl: '{{ route('activity-logs.index') }}',
        filterForm: '#filter-logs-form',
        searchInput: '#filter-log-search',
        resetButton: '#btn-reset-log-filter',
        pageLength: 25,
        order: [[0, 'desc']],
        columns: [
            {
                data: 'created_at',
                name: 'created_at',
                className: 'small text-muted font-monospace',
            },
            {
                data: 'user_name',
                name: 'user_id',
                render: function (data) {
                    return `<span class="fw-semibold text-dark">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'user_role',
                name: 'user_role',
                render: function (data) {
                    return `<span class="badge bg-light text-dark border small">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'pop_name',
                name: 'pop_id',
                render: function (data) { return `<span class="badge bg-light text-dark border font-monospace">${window.escapeHtml(data)}</span>`; }
            },
            {
                data: 'action',
                name: 'action',
                render: function (data) {
                    return `<span class="font-monospace small text-primary fw-semibold">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'description',
                name: 'description',
                render: function (data) {
                    return `<span class="small text-break">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'ip',
                name: 'ip',
                className: 'small font-monospace text-muted',
                render: function (data) {
                    return window.escapeHtml(data);
                }
            }
        ]
    });
});
</script>
@endpush

