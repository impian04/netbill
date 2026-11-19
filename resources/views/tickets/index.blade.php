@extends('layouts.app')

@section('title', 'Tiket Komplain NOC')

@section('content')
@php
    $user = auth()->user();
    $canManage = $user->isSuperAdmin() || $user->isNoc();
    $isSuperAdmin = $user->isSuperAdmin();
@endphp

<x-page-header title="Tiket Komplain NOC" subtitle="Manajemen dan pelacakan tiket keluhan atau permintaan pelanggan.">
    <x-btn id="btn-export-tickets" variant="export" href="{{ route('export.tickets') }}" title="Ekspor sesuai filter aktif">
        Ekspor Excel
    </x-btn>
    @if($canManage)
        <x-btn variant="primary" id="btn-create-ticket">
            Buat Tiket
        </x-btn>
    @endif
</x-page-header>

{{-- Stat Cards --}}
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
    <div class="col">
        <x-stat-card border-color="#2563eb" title="Terbuka" value="{{ number_format($stats['open']) }}" color="primary" />
    </div>
    <div class="col">
        <x-stat-card border-color="#0891b2" title="Sedang Diproses" value="{{ number_format($stats['in_progress']) }}" color="info" />
    </div>
    <div class="col">
        <x-stat-card border-color="#dc2626" title="Mendesak" value="{{ number_format($stats['urgent']) }}" color="danger" />
    </div>
    <div class="col">
        <x-stat-card border-color="#d97706" title="Resp. Pertama (30h)" value="{{ $stats['avg_response'] }}" color="warning" />
    </div>
    <div class="col">
        <x-stat-card border-color="#16a34a" title="Penyelesaian (30h)" value="{{ $stats['avg_resolution'] }}" color="success" />
    </div>
</div>

{{-- Filter Card --}}
<x-filter-card>
    <form id="filterTicketsForm" class="row g-2 align-items-end">
    @if($isSuperAdmin || $user->isNoc())
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Filter POP</label>
            <select name="pop_id" id="filter_pop_id" class="form-select form-select-sm">
                <option value="">-- Semua POP --</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}">{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="col-12 col-md-2">
        <label class="form-label small fw-semibold">Status</label>
        <select name="status" id="filter_status" class="form-select form-select-sm">
            <option value="">-- Semua Status --</option>
            @foreach($statuses as $st)
                <option value="{{ $st->value }}">{{ $st->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 col-md-2">
        <label class="form-label small fw-semibold">Kategori</label>
        <select name="category" id="filter_category" class="form-select form-select-sm">
            <option value="">-- Semua Kategori --</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 col-md-2">
        <label class="form-label small fw-semibold">Prioritas</label>
        <select name="priority" id="filter_priority" class="form-select form-select-sm">
            <option value="">-- Semua Prioritas --</option>
            @foreach($priorities as $prio)
                <option value="{{ $prio->value }}">{{ $prio->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold">Ditangani Oleh</label>
        <select name="assigned_to" id="filter_assigned_to" class="form-select form-select-sm">
            <option value="">-- Semua Staf NOC --</option>
            @foreach($nocUsers as $nu)
                <option value="{{ $nu->id }}">{{ $nu->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold">Mulai Tanggal</label>
        <input type="date" name="start_date" id="filter_start_date" class="form-control form-control-sm">
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold">Sampai Tanggal</label>
        <input type="date" name="end_date" id="filter_end_date" class="form-control form-control-sm">
    </div>

    <div class="col-12 col-md-3 d-flex align-items-end">
        <div class="form-check mb-1">
            <input class="form-check-input" type="checkbox" name="my_tickets" id="filter_my_tickets" value="1">
            <label class="form-check-label small fw-semibold" for="filter_my_tickets">
                Tiket Saya Saja
            </label>
        </div>
    </div>

    <div class="col-12 col-md-3 d-flex align-items-end justify-content-md-end">
        <x-btn type="reset" variant="neutral" id="btn-reset-filters">Reset</x-btn>
    </div>
    </form>
</x-filter-card>

{{-- Table Card --}}
<x-table-card title="Daftar Tiket Komplain">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 w-100" id="ticketsTable">
            <thead class="table-light">
                <tr>
                    <th>Nomor Tiket</th>
                    <th>Tanggal</th>
                    <th>Pelapor / Pelanggan</th>
                    <th>POP</th>
                    <th>Kategori</th>
                    <th>Prioritas</th>
                    <th>Status</th>
                    <th>Ditangani Oleh</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</x-table-card>

@if($canManage)
{{-- Offcanvas Buat / Edit Tiket --}}
<x-offcanvas-form
    id="ticketFormOffcanvas"
    formId="ticketForm"
    title="Buat Tiket Baru"
    titleId="ticketFormTitle"
    subtitle="Isi informasi pengaduan atau keluhan pelanggan."
>
    <input type="hidden" name="ticket_id" id="form_ticket_id">

    {{-- Mode Pelapor: Pelanggan Terdaftar vs Umum --}}
    <div class="mb-3" id="reporter-type-container">
        <label class="form-label small fw-semibold">Tipe Pelapor</label>
        <div class="d-flex gap-3">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="reporter_type" id="reporter_type_customer" value="customer" checked>
                <label class="form-check-label" for="reporter_type_customer">Pelanggan Terdaftar</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="reporter_type" id="reporter_type_general" value="general">
                <label class="form-check-label" for="reporter_type_general">Bukan Pelanggan / Umum</label>
            </div>
        </div>
    </div>

    {{-- Box Pencarian Pelanggan --}}
    <div id="customer-search-section" class="mb-3">
        <label class="form-label small fw-semibold">Cari Pelanggan <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
            <input type="text" class="form-control form-control-sm" id="customer_search_input" placeholder="Ketik kode, nama, atau telepon (min. 2 karakter)..." autocomplete="off">
        </div>
        <div id="customer-search-results" class="list-group position-absolute shadow-sm mt-1 w-100 z-3 d-none overflow-auto"></div>
        <input type="hidden" name="customer_id" id="form_customer_id">

        {{-- Info Box Pelanggan Terpilih --}}
        <div id="selected-customer-box" class="card card-body bg-light mt-2 p-2 d-none">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-bold" id="sel_customer_name">-</div>
                    <div class="small text-muted">
                        <span id="sel_customer_code"></span> &bull; <span id="sel_customer_pop"></span> &bull; <span id="sel_customer_phone"></span>
                    </div>
                </div>
                <x-btn variant="danger" type="button"   id="btn-clear-customer" title="Hapus Pilihan">
                    &times;
                </x-btn>
            </div>
        </div>

        {{-- Peringatan Tiket Terbuka Lain --}}
        <div id="customerWarningAlert" class="alert alert-warning mt-2 mb-0 py-2 px-3 small d-none" role="alert">
            <span id="customerWarningText">Perhatian: Pelanggan ini memiliki tiket yang masih aktif.</span>
        </div>
        <div class="invalid-feedback d-none" id="customer_id-error"></div>
    </div>

    {{-- Form Pelapor Umum --}}
    <div id="general-reporter-section" class="mb-3 d-none">
        <div class="row g-2">
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Nama Pelapor <span class="text-danger">*</span></label>
                <input type="text" name="reporter_name" id="form_reporter_name" class="form-control form-control-sm" maxlength="120">
                <div class="invalid-feedback"></div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Nomor Telepon <span class="text-danger">*</span></label>
                <input type="text" name="reporter_phone" id="form_reporter_phone" class="form-control form-control-sm" maxlength="30">
                <div class="invalid-feedback"></div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">POP Terkait <span class="text-danger">*</span></label>
                <select name="pop_id" id="form_pop_id" class="form-select form-select-sm">
                    <option value="">-- Pilih POP --</option>
                    @foreach($pops as $pop)
                        <option value="{{ $pop->id }}">{{ $pop->name }}</option>
                    @endforeach
                </select>
                <div class="invalid-feedback"></div>
            </div>
        </div>
    </div>

    {{-- Metadata Tiket --}}
    <div class="row g-2 mb-3">
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Kategori <span class="text-danger">*</span></label>
            <select name="category" id="form_category" class="form-select form-select-sm" required>
                @foreach($categories as $cat)
                    <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback"></div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Saluran Pengaduan <span class="text-danger">*</span></label>
            <select name="channel" id="form_channel" class="form-select form-select-sm" required>
                @foreach($channels as $chn)
                    <option value="{{ $chn->value }}">{{ $chn->label() }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback"></div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Prioritas <span class="text-danger">*</span></label>
            <select name="priority" id="form_priority" class="form-select form-select-sm" required>
                @foreach($priorities as $pri)
                    <option value="{{ $pri->value }}" {{ $pri->value === 'NORMAL' ? 'selected' : '' }}>{{ $pri->label() }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback"></div>
        </div>
    </div>

    <div class="mb-3" id="assigned-to-container">
        <label class="form-label small fw-semibold">Tugaskan ke Staf NOC</label>
        <select name="assigned_to" id="form_assigned_to" class="form-select form-select-sm">
            <option value="">-- Belum Ditugaskan --</option>
            @foreach($nocUsers as $nu)
                <option value="{{ $nu->id }}">{{ $nu->name }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback"></div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Subjek Keluhan <span class="text-danger">*</span></label>
        <input type="text" name="subject" id="form_subject" class="form-control form-control-sm" maxlength="120" placeholder="Ringkasan singkat kendala..." required>
        <div class="invalid-feedback"></div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Deskripsi Kendala <span class="text-danger">*</span></label>
        <textarea name="description" id="form_description" class="form-control form-control-sm" rows="4" maxlength="2000" placeholder="Detail kendala yang dilaporkan pelanggan..." required></textarea>
        <div class="invalid-feedback"></div>
    </div>
</x-offcanvas-form>
@endif

{{-- Offcanvas Detail Tiket & Timeline --}}
<x-offcanvas-form
    id="ticketDetailOffcanvas"
    title="Detail Tiket"
    titleId="ticketDetailTitle">
    <x-slot:header>
        <div>
            <div class="d-flex align-items-center gap-2">
                <h5 class="offcanvas-title mb-0" id="ticketDetailTitle">Detail Tiket</h5>
                <span id="dtl_status_badge">-</span>
                <span id="dtl_priority_badge">-</span>
            </div>
            <div class="small text-muted" id="dtl_ticket_number"></div>
        </div>
    </x-slot:header>

        {{-- Section 1: Ringkasan Tiket --}}
        <div class="card mb-3 border-0 bg-light">
            <div class="card-body p-3">
                <div class="row g-2 small">
                    <div class="col-12 col-sm-6">
                        <span class="text-muted d-block">Pelapor:</span>
                        <strong id="dtl_reporter_name">-</strong>
                        <div id="dtl_reporter_phone" class="text-muted"></div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <span class="text-muted d-block">Pelanggan Terdaftar:</span>
                        <div id="dtl_customer_info">-</div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="text-muted d-block">POP:</span>
                        <span id="dtl_pop_name" class="fw-semibold">-</span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="text-muted d-block">Kategori:</span>
                        <span id="dtl_category">-</span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="text-muted d-block">Saluran:</span>
                        <span id="dtl_channel">-</span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="text-muted d-block">Ditugaskan Kepada:</span>
                        <strong id="dtl_assignee_name">-</strong>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="text-muted d-block">Waktu Buka:</span>
                        <span id="dtl_opened_at">-</span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="text-muted d-block">Respons Pertama:</span>
                        <span id="dtl_first_response_at">-</span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="text-muted d-block">Terselesaikan:</span>
                        <span id="dtl_resolved_at">-</span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="text-muted d-block">Ditutup:</span>
                        <span id="dtl_closed_at">-</span>
                    </div>
                </div>
                <hr class="my-2">
                <div>
                    <div class="text-muted small">Subjek:</div>
                    <div class="fw-bold mb-1" id="dtl_subject">-</div>
                    <div class="text-muted small">Deskripsi:</div>
                    <div class="p-2 bg-white rounded border small text-break" id="dtl_description">-</div>
                </div>
                <div id="dtl_resolution_box" class="mt-2 p-2 bg-success-subtle border border-success rounded small d-none">
                    <strong>Catatan Penyelesaian:</strong>
                    <div id="dtl_resolution_note"></div>
                </div>
            </div>
        </div>

        {{-- Section 2: Aksi NOC & Super Admin (Status, Tugaskan, Catatan) --}}
        @if($canManage)
        <div id="dtl_actions_section" class="card mb-3 border">
            <div class="card-header bg-white py-2 fw-semibold small">
                Aksi Operasional Tiket
            </div>
            <div class="card-body p-3">
                {{-- Form Ubah Status --}}
                <div class="mb-3" id="change_status_container">
                    <label class="form-label small fw-semibold">Ubah Status</label>
                    <div class="row g-2">
                        <div class="col-8">
                            <select id="dtl_target_status" class="form-select form-select-sm">
                                <option value="">-- Pilih Transisi Status --</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <x-btn variant="primary" class="w-100" id="btn-save-status">
                                Ubah Status
                            </x-btn>
                        </div>
                    </div>
                    {{-- Input catatan penyelesaian (wajib saat RESOLVED) --}}
                    <div id="resolution_note_wrapper" class="mt-2 d-none">
                        <label class="form-label small text-danger fw-semibold">Catatan Penyelesaian (Wajib) <span class="text-danger">*</span></label>
                        <textarea id="dtl_resolution_input" class="form-control form-control-sm" rows="2" placeholder="Jelaskan tindakan perbaikan/penyelesaian..."></textarea>
                    </div>
                </div>

                {{-- Form Tugaskan --}}
                <div class="mb-3" id="assign_container">
                    <label class="form-label small fw-semibold">Alihkan Penugasan Staf NOC</label>
                    <div class="row g-2">
                        <div class="col-8">
                            <select id="dtl_assign_select" class="form-select form-select-sm">
                                <option value="">-- Belum Ditugaskan --</option>
                                @foreach($nocUsers as $nu)
                                    <option value="{{ $nu->id }}">{{ $nu->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4">
                            <x-btn variant="neutral" class="w-100" id="btn-save-assign">
                                Tugaskan
                            </x-btn>
                        </div>
                    </div>
                </div>

                {{-- Form Tambah Catatan --}}
                <div id="note_container">
                    <label class="form-label small fw-semibold">Tambah Catatan Internal</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="dtl_note_input" class="form-control form-control-sm" placeholder="Tulis catatan penanganan internal...">
                        <x-btn variant="primary" type="button" id="btn-save-note">
                            Kirim
                        </x-btn>
                    </div>
                </div>

                {{-- Minta Kunjungan Teknisi --}}
                <div class="mt-3 d-none" id="field_visit_request_container">
                    <div class="p-2 bg-warning-subtle border border-warning rounded">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            Minta Kunjungan Teknisi (Perbaikan)
                        </label>
                        <p class="small text-muted mb-2">
                            Mengajukan work order perbaikan ke Admin POP. Tiket akan berubah ke status Kunjungan Lapangan.
                        </p>
                        <textarea id="dtl_field_visit_note" class="form-control form-control-sm mb-2" rows="2" placeholder="Tuliskan ringkasan kendala atau instruksi untuk teknisi..."></textarea>
                        <x-btn variant="warning" class="w-100" id="btn-request-field-visit">
                            Ajukan Kunjungan Teknisi
                        </x-btn>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Section 3: Timeline Perjalanan Tiket --}}
        <div class="card border">
            <div class="card-header bg-white py-2 fw-semibold small">
                Catatan &amp; Riwayat Aktivitas
            </div>
            <div class="card-body p-3">
                <div id="ticket_timeline_list" class="position-relative">
                    <div class="text-center text-muted small py-3">Memuat riwayat...</div>
                </div>
            </div>
        </div>

    <x-slot:footer>
        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
            <x-btn variant="neutral" data-bs-dismiss="offcanvas">Tutup</x-btn>
            @if($canManage)
                <x-btn variant="primary" class="d-none" id="btn-edit-ticket-data">
                    Edit Informasi Tiket
                </x-btn>
            @endif
        </div>
    </x-slot:footer>
</x-offcanvas-form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isSuperAdmin = @json($isSuperAdmin);
    const canManage = @json($canManage);
    let currentTicketId = null;
    let customerDebounceTimer = null;

    // 1. Inisialisasi DataTable Tiket Server-Side
    const tableColumns = [
        {
            data: 'ticket_number',
            render: function (data, type, row) {
                return `<a href="javascript:void(0)" class="fw-bold text-decoration-none btn-detail" data-id="${row.id}">${window.escapeHtml(data)}</a>
                        <div class="small text-muted text-truncate mw-180">${window.escapeHtml(row.subject || '')}</div>`;
            }
        },
        { data: 'opened_at' },
        {
            data: 'reporter',
            render: function (data) {
                let html = `<div class="fw-semibold">${window.escapeHtml(data.name || '-')}</div>`;
                if (data.customer_code) {
                    html += `<span class="badge bg-light text-dark border">${window.escapeHtml(data.customer_code)}</span> `;
                }
                if (data.phone) {
                    html += `<span class="small text-muted">${window.escapeHtml(data.phone)}</span>`;
                }
                return html;
            }
        },
        { data: 'pop_name' },
        {
            data: 'category',
            render: function (data) {
                return `<span class="badge bg-${data.badge}">${window.escapeHtml(data.label)}</span>`;
            }
        },
        {
            data: 'priority',
            render: function (data) {
                return window.statusBadge('ticket_priority', data.value || data.label || data);
            }
        },
        {
            data: 'status',
            render: function (data) {
                return window.statusBadge('ticket_status', data.value || data.label || data);
            }
        },
        {
            data: 'assignee',
            render: function (data) {
                return `<span class="small">${window.escapeHtml(data)}</span>`;
            }
        },
        {
            data: null,
            orderable: false,
            className: 'text-end',
            render: function (data, type, row) {
                return window.btnIconHtml('view', {
                    class: 'btn-detail',
                    'data-id': row.id,
                    title: 'Lihat Detail'
                });
            }
        }
    ];

    const table = window.initServerTable('#ticketsTable', {
        ajaxUrl: '{{ route('tickets.index') }}',
        filterForm: '#filterTicketsForm',
        columns: tableColumns,
        order: [[1, 'desc']],
    });

    // 2. Logika Buat & Ubah Tiket (NOC / Super Admin)
    const btnCreateTicket = document.getElementById('btn-create-ticket');
    const ticketFormOffcanvas = document.getElementById('ticketFormOffcanvas');
    const ticketForm = document.getElementById('ticketForm');

    btnCreateTicket?.addEventListener('click', function () {
        currentTicketId = null;
        ticketForm?.reset();
        document.getElementById('form_ticket_id').value = '';
        document.getElementById('ticketFormTitle').textContent = 'Buat Tiket Baru';
        document.getElementById('reporter_type_customer').checked = true;
        document.getElementById('reporter-type-container').classList.remove('d-none');
        document.getElementById('customer-search-section').classList.remove('d-none');
        document.getElementById('general-reporter-section').classList.add('d-none');
        document.getElementById('selected-customer-box').classList.add('d-none');
        document.getElementById('customerWarningAlert').classList.add('d-none');
        document.getElementById('form_customer_id').value = '';
        window.showOffcanvas(ticketFormOffcanvas);
    });

    document.querySelectorAll('input[name="reporter_type"]').forEach(r => {
        r.addEventListener('change', function () {
            if (this.value === 'customer') {
                document.getElementById('customer-search-section').classList.remove('d-none');
                document.getElementById('general-reporter-section').classList.add('d-none');
            } else {
                document.getElementById('customer-search-section').classList.add('d-none');
                document.getElementById('general-reporter-section').classList.remove('d-none');
                document.getElementById('form_customer_id').value = '';
                document.getElementById('customerWarningAlert').classList.add('d-none');
            }
        });
    });

    // Pencarian Pelanggan dengan Debounce
    const customerSearchInput = document.getElementById('customer_search_input');
    const customerSearchResults = document.getElementById('customer-search-results');

    customerSearchInput?.addEventListener('input', function () {
        clearTimeout(customerDebounceTimer);
        const query = this.value.trim();
        if (query.length < 2) {
            customerSearchResults.innerHTML = '';
            customerSearchResults.classList.add('d-none');
            return;
        }

        customerDebounceTimer = setTimeout(async () => {
            try {
                const res = await window.apiRequest(`{{ route('tickets.search-customers') }}?q=${encodeURIComponent(query)}`);
                customerSearchResults.innerHTML = '';
                if (!res || res.length === 0) {
                    customerSearchResults.innerHTML = '<div class="list-group-item small text-muted">Pelanggan tidak ditemukan.</div>';
                    customerSearchResults.classList.remove('d-none');
                    return;
                }

                res.forEach(item => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action py-2';
                    btn.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>${window.escapeHtml(item.name)}</strong>
                            <span class="badge bg-secondary">${window.escapeHtml(item.customer_code)}</span>
                        </div>
                        <div class="small text-muted">${window.escapeHtml(item.pop_name)} &bull; ${window.escapeHtml(item.phone || '-')}</div>
                    `;
                    btn.addEventListener('click', () => selectCustomer(item));
                    customerSearchResults.appendChild(btn);
                });
                customerSearchResults.classList.remove('d-none');
            } catch (err) {
                console.error(err);
            }
        }, 300);
    });

    function selectCustomer(cust) {
        document.getElementById('form_customer_id').value = cust.id;
        document.getElementById('sel_customer_name').textContent = cust.name;
        document.getElementById('sel_customer_code').textContent = cust.customer_code;
        document.getElementById('sel_customer_pop').textContent = cust.pop_name;
        document.getElementById('sel_customer_phone').textContent = cust.phone || '-';
        document.getElementById('selected-customer-box').classList.remove('d-none');

        const warnAlert = document.getElementById('customerWarningAlert');
        const warnText = document.getElementById('customerWarningText');
        if (cust.open_tickets_count > 0) {
            warnText.textContent = `Perhatian: Pelanggan ini memiliki ${cust.open_tickets_count} tiket lain yang masih aktif/terbuka.`;
            warnAlert.classList.remove('d-none');
        } else {
            warnAlert.classList.add('d-none');
        }

        customerSearchResults.innerHTML = '';
        customerSearchResults.classList.add('d-none');
        customerSearchInput.value = '';
    }

    document.getElementById('btn-clear-customer')?.addEventListener('click', function () {
        document.getElementById('form_customer_id').value = '';
        document.getElementById('selected-customer-box').classList.add('d-none');
        document.getElementById('customerWarningAlert').classList.add('d-none');
    });

    if (ticketForm) {
        window.initCrudOffcanvas({
            offcanvasId: '#ticketFormOffcanvas',
            formId: '#ticketForm',
            createUrl: '{{ route('tickets.store') }}',
            updateUrlTemplate: '{{ url('tickets') }}/:id',
            entityName: 'Tiket',
            table: table,
            onSuccess: function (res) {
                window.toast('success', res.message || 'Tiket berhasil disimpan.');
                window.hideOffcanvas(ticketFormOffcanvas);
            }
        });
    }

    // 3. Detail Offcanvas & Timeline Tiket
    const detailOffcanvasEl = document.getElementById('ticketDetailOffcanvas');

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-detail');
        if (!btn) return;

        const id = btn.getAttribute('data-id');
        currentTicketId = id;

        try {
            const data = await window.apiRequest(`{{ url('tickets') }}/${id}`);
            renderTicketDetail(data);
            window.showOffcanvas(detailOffcanvasEl);
        } catch (err) {
            window.toast('error', err.message || 'Gagal memuat detail tiket.');
        }
    });

    function renderTicketDetail(data) {
        const t = data.ticket;
        document.getElementById('dtl_ticket_number').textContent = t.ticket_number;
        document.getElementById('dtl_status_badge').innerHTML = window.statusBadge('ticket_status', t.status.value || t.status.label || t.status);
        document.getElementById('dtl_priority_badge').innerHTML = window.statusBadge('ticket_priority', t.priority.value || t.priority.label || t.priority);

        document.getElementById('dtl_reporter_name').textContent = t.reporter_name || '-';
        document.getElementById('dtl_reporter_phone').textContent = t.reporter_phone ? `Telp: ${t.reporter_phone}` : '';

        if (t.customer) {
            document.getElementById('dtl_customer_info').innerHTML = `
                <strong>${window.escapeHtml(t.customer.name)}</strong> (${window.escapeHtml(t.customer.customer_code)})
                <div class="text-muted">${window.escapeHtml(t.customer.package || '-')} &bull; Status: ${window.escapeHtml(t.customer.service_status || '-')}</div>
            `;
        } else {
            document.getElementById('dtl_customer_info').textContent = 'Pelapor Umum (Non-Pelanggan)';
        }

        document.getElementById('dtl_pop_name').textContent = t.pop_name || '-';
        document.getElementById('dtl_category').textContent = t.category.label;
        document.getElementById('dtl_channel').textContent = t.channel.label;
        document.getElementById('dtl_assignee_name').textContent = t.assignee_name;
        document.getElementById('dtl_opened_at').textContent = t.opened_at || '-';
        document.getElementById('dtl_first_response_at').textContent = t.first_response_at || '-';
        document.getElementById('dtl_resolved_at').textContent = t.resolved_at || '-';
        document.getElementById('dtl_closed_at').textContent = t.closed_at || '-';

        document.getElementById('dtl_subject').textContent = t.subject;
        document.getElementById('dtl_description').textContent = t.description;

        const resBox = document.getElementById('dtl_resolution_box');
        if (t.resolution_note) {
            document.getElementById('dtl_resolution_note').textContent = t.resolution_note;
            resBox.classList.remove('d-none');
        } else {
            resBox.classList.add('d-none');
        }

        const targetStatusSelect = document.getElementById('dtl_target_status');
        const actionsSection = document.getElementById('dtl_actions_section');
        const btnEditTicket = document.getElementById('btn-edit-ticket-data');

        if (targetStatusSelect) {
            targetStatusSelect.innerHTML = '<option value="">-- Pilih Transisi Status --</option>';
            if (data.allowed_transitions && data.allowed_transitions.length > 0) {
                data.allowed_transitions.forEach(tr => {
                    const opt = document.createElement('option');
                    opt.value = tr.value;
                    opt.textContent = tr.label;
                    targetStatusSelect.appendChild(opt);
                });
                document.getElementById('change_status_container')?.classList.remove('d-none');
            } else {
                document.getElementById('change_status_container')?.classList.add('d-none');
            }
        }

        if (actionsSection) {
            if (data.can_manage) {
                actionsSection.classList.remove('d-none');
                btnEditTicket?.classList.remove('d-none');
            } else {
                actionsSection.classList.add('d-none');
                btnEditTicket?.classList.add('d-none');
            }
        }

        if (document.getElementById('dtl_assign_select')) {
            document.getElementById('dtl_assign_select').value = t.assignee_id || '';
        }

        const fvContainer = document.getElementById('field_visit_request_container');
        if (fvContainer) {
            if (data.can_request_field_visit) {
                fvContainer.classList.remove('d-none');
                document.getElementById('dtl_field_visit_note').value = '';
            } else {
                fvContainer.classList.add('d-none');
            }
        }

        renderTimeline(data.updates);
    }

    function renderTimeline(updates) {
        const list = document.getElementById('ticket_timeline_list');
        list.innerHTML = '';
        if (!updates || updates.length === 0) {
            list.innerHTML = '<div class="text-center text-muted small py-2">Belum ada riwayat update.</div>';
            return;
        }

        updates.forEach(u => {
            const item = document.createElement('div');
            item.className = 'border-start border-2 ps-3 pb-3 position-relative';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div>
                        <span class="badge bg-${u.type_badge}">${window.escapeHtml(u.type_label)}</span>
                        <strong class="ms-1 small">${window.escapeHtml(u.user_name)}</strong>
                    </div>
                    <span class="text-muted fs-xs">${window.escapeHtml(u.created_at)}</span>
                </div>
                ${u.from_status ? `<div class="small text-muted mb-1">Perubahan: <span class="badge bg-secondary">${window.escapeHtml(u.from_status)}</span> &rarr; <span class="badge bg-info">${window.escapeHtml(u.to_status)}</span></div>` : ''}
                ${u.body ? `<div class="p-2 bg-light rounded small border">${window.escapeHtml(u.body)}</div>` : ''}
            `;
            list.appendChild(item);
        });
    }

    document.getElementById('dtl_target_status')?.addEventListener('change', function () {
        const wrap = document.getElementById('resolution_note_wrapper');
        if (this.value === 'RESOLVED') {
            wrap.classList.remove('d-none');
        } else {
            wrap.classList.add('d-none');
        }
    });

    document.getElementById('btn-save-status')?.addEventListener('click', async function () {
        const targetStatus = document.getElementById('dtl_target_status').value;
        if (!targetStatus) {
            window.toast('warning', 'Pilih status tujuan terlebih dahulu.');
            return;
        }
        const resolutionNote = document.getElementById('dtl_resolution_input')?.value;
        if (targetStatus === 'RESOLVED' && !resolutionNote.trim()) {
            window.toast('warning', 'Catatan penyelesaian wajib diisi saat menyelesaikan tiket.');
            return;
        }

        const confirmed = await window.confirmDialog({
            title: 'Konfirmasi Ubah Status',
            text: `Apakah Anda yakin ingin mengubah status tiket ini?`,
            icon: 'question'
        });
        if (!confirmed) return;

        try {
            const res = await window.apiRequest(`{{ url('tickets') }}/${currentTicketId}/status`, {
                method: 'POST',
                body: JSON.stringify({
                    status: targetStatus,
                    resolution_note: resolutionNote
                })
            });
            window.toast('success', res.message || 'Status berhasil diubah.');
            table?.ajax?.reload();
            const fresh = await window.apiRequest(`{{ url('tickets') }}/${currentTicketId}`);
            renderTicketDetail(fresh);
        } catch (err) {
            window.toast('error', err.message || 'Gagal mengubah status tiket.');
        }
    });

    document.getElementById('btn-save-assign')?.addEventListener('click', async function () {
        const assigneeId = document.getElementById('dtl_assign_select').value;
        try {
            const res = await window.apiRequest(`{{ url('tickets') }}/${currentTicketId}/assign`, {
                method: 'POST',
                body: JSON.stringify({ assigned_to: assigneeId || null })
            });
            window.toast('success', res.message || 'Penugasan berhasil diperbarui.');
            table?.ajax?.reload();
            const fresh = await window.apiRequest(`{{ url('tickets') }}/${currentTicketId}`);
            renderTicketDetail(fresh);
        } catch (err) {
            window.toast('error', err.message || 'Gagal memperbarui penugasan.');
        }
    });

    document.getElementById('btn-save-note')?.addEventListener('click', async function () {
        const noteInput = document.getElementById('dtl_note_input');
        const body = noteInput.value.trim();
        if (!body) {
            window.toast('warning', 'Tuliskan catatan terlebih dahulu.');
            return;
        }

        try {
            const res = await window.apiRequest(`{{ url('tickets') }}/${currentTicketId}/notes`, {
                method: 'POST',
                body: JSON.stringify({ body: body })
            });
            noteInput.value = '';
            window.toast('success', res.message || 'Catatan berhasil ditambahkan.');
            const fresh = await window.apiRequest(`{{ url('tickets') }}/${currentTicketId}`);
            renderTicketDetail(fresh);
        } catch (err) {
            window.toast('error', err.message || 'Gagal menambahkan catatan.');
        }
    });

    document.getElementById('btn-request-field-visit')?.addEventListener('click', async function () {
        const note = document.getElementById('dtl_field_visit_note')?.value.trim();
        if (!note || note.length < 5) {
            window.toast('warning', 'Tuliskan ringkasan kendala minimal 5 karakter.');
            return;
        }

        const confirmed = await window.confirmDialog({
            title: 'Konfirmasi Kunjungan Teknisi',
            text: 'Ajukan permintaan penugasan teknisi perbaikan ke Admin POP?',
            icon: 'question'
        });
        if (!confirmed) return;

        try {
            const res = await window.apiRequest(`{{ url('tickets') }}/${currentTicketId}/request-field-visit`, {
                method: 'POST',
                body: JSON.stringify({ admin_note: note })
            });
            window.toast('success', res.message || 'Permintaan kunjungan teknisi berhasil diajukan.');
            table?.ajax?.reload();
            const fresh = await window.apiRequest(`{{ url('tickets') }}/${currentTicketId}`);
            renderTicketDetail(fresh);
        } catch (err) {
            window.toast('error', err.message || 'Gagal mengajukan kunjungan teknisi.');
        }
    });

    // Ekspor Tiket
    document.getElementById('btn-export-tickets')?.addEventListener('click', function (e) {
        e.preventDefault();
        const form = document.getElementById('filterTicketsForm');
        const params = new URLSearchParams();
        if (form) {
            new FormData(form).forEach((val, key) => { if (val) params.set(key, val); });
        }
        window.location.href = '{{ route('export.tickets') }}' + (params.toString() ? '?' + params.toString() : '');
    });

    // Edit Data Tiket
    document.getElementById('btn-edit-ticket-data')?.addEventListener('click', async function () {
        try {
            const res = await window.apiRequest(`{{ url('tickets') }}/${currentTicketId}`);
            const t = res.ticket;
            document.getElementById('ticketFormTitle').textContent = `Ubah Tiket ${t.ticket_number}`;
            document.getElementById('form_ticket_id').value = t.id;
            document.getElementById('reporter-type-container').classList.add('d-none');
            document.getElementById('customer-search-section').classList.add('d-none');
            document.getElementById('general-reporter-section').classList.add('d-none');
            document.getElementById('form_category').value = t.category.value;
            document.getElementById('form_channel').value = t.channel.value;
            document.getElementById('form_priority').value = t.priority.value;
            document.getElementById('form_subject').value = t.subject;
            document.getElementById('form_description').value = t.description;
            document.getElementById('assigned-to-container').classList.add('d-none');

            window.hideOffcanvas(detailOffcanvasEl);
            window.showOffcanvas(ticketFormOffcanvas);
        } catch (err) {
            window.toast('error', err.message || 'Gagal memuat data tiket.');
        }
    });
});
</script>
@endpush
@endsection
