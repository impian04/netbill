@extends('layouts.app')

@section('title', 'Manajemen Survei')

@section('content')
<x-page-header title="Manajemen Survei Lapangan" subtitle="Kelola dan verifikasi hasil survei teknis calon pelanggan baru.">
    <x-btn id="btn-export-installation" variant="export" href="{{ route('export.installation-report') }}" title="Ekspor laporan pemasangan sesuai filter">
        Ekspor Laporan
    </x-btn>
</x-page-header>

{{-- Stat Cards --}}
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
    <div class="col">
        <x-stat-card border-color="#2563eb" title="Total Survei" value="{{ number_format($stats['total']) }}" color="primary" />
    </div>
    <div class="col">
        <x-stat-card border-color="#0891b2" title="Menunggu Peninjauan" value="{{ number_format($stats['submitted']) }}" color="info" />
    </div>
    <div class="col">
        <x-stat-card border-color="#d97706" title="Perlu Survei Ulang" value="{{ number_format($stats['resurvey_requested']) }}" color="warning" />
    </div>
    <div class="col">
        <x-stat-card border-color="#16a34a" title="Ditugaskan Teknisi" value="{{ number_format($stats['assigned']) }}" color="success" />
    </div>
    <div class="col">
        <x-stat-card border-color="#64748b" title="Ditutup (Batal)" value="{{ number_format($stats['closed']) }}" color="secondary" />
    </div>
</div>

{{-- Filter Card --}}
<x-filter-card>
    <form id="filter-surveys-form" class="row g-2 align-items-end">
        @if($isSuperAdmin)
            <div class="col-12 col-md-2">
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
                @foreach(\App\Enums\SurveyStatus::cases() as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Surveyor</label>
            <select name="surveyor_id" id="filter_surveyor_id" class="form-select form-select-sm">
                <option value="">-- Semua Surveyor --</option>
                @foreach($surveyors as $sv)
                    <option value="{{ $sv->id }}">{{ $sv->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">ODC</label>
            <select name="odc_id" id="filter_odc_id" class="form-select form-select-sm">
                <option value="">-- Semua ODC --</option>
                @foreach($odcs as $odc)
                    <option value="{{ $odc->id }}">{{ $odc->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Tanggal Survei</label>
            <div class="input-group input-group-sm">
                <input type="date" name="from" id="filter_date_from" class="form-control form-control-sm" title="Dari Tanggal">
                <span class="input-group-text">-</span>
                <input type="date" name="to" id="filter_date_to" class="form-control form-control-sm" title="Sampai Tanggal">
            </div>
        </div>
        <div class="col-12 col-md-auto ms-auto d-flex align-items-end">
            <x-btn id="btn-reset-surveys-filter" variant="neutral" type="reset" class="btn-sm w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- Table Card --}}
<x-table-card id="surveys-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 w-100" id="surveysTable">
            <thead class="table-light">
                <tr>
                    <th>Kode Pelanggan</th>
                    <th>Nama Pelanggan</th>
                    @if($isSuperAdmin)
                        <th>POP</th>
                    @endif
                    <th>Surveyor</th>
                    <th>ODC / ODP</th>
                    <th>Hasil</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</x-table-card>

{{-- Offcanvas Detail Survei --}}
<x-offcanvas-form
    id="detailSurveyOffcanvas"
    title="Detail Hasil Survei"
    titleId="detailSurveyTitle"
    subtitle="-"
    subtitleId="detailSurveySubtitle">
        {{-- Section 1: Data Pelanggan --}}
        <div class="card border-0 bg-light mb-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="badge bg-secondary me-1" id="dt_customer_code">-</span>
                        <strong class="text-dark" id="dt_customer_name">-</strong>
                    </div>
                    <x-btn-icon action="route" href="#" target="_blank" rel="noopener noreferrer" id="dt_route_btn" title="Buka Rute Maps" />
                </div>
                <div class="small text-muted mb-1">
                    <strong>Alamat:</strong> <span id="dt_customer_address" class="text-dark">-</span>
                </div>
                <div class="small text-muted mb-1">
                    <strong>Telepon:</strong> <span id="dt_customer_phone" class="text-dark">-</span>
                </div>
                <div class="small text-muted">
                    <strong>Koordinat:</strong> <span id="dt_coordinates" class="text-dark">-</span>
                </div>
            </div>
        </div>

        {{-- Section 2: Hasil Survei Lapangan --}}
        <div class="border rounded p-3 mb-3">
            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                Hasil Pemeriksaan Lapangan
            </h6>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <div class="small text-muted">Hasil Kelayakan:</div>
                    <span id="dt_result_badge">-</span>
                </div>
                <div class="col-6">
                    <div class="small text-muted">Status Survei:</div>
                    <span id="dt_status_badge">-</span>
                </div>
                <div class="col-6">
                    <div class="small text-muted">ODC Terpilih:</div>
                    <strong class="text-dark small" id="dt_odc_name">-</strong>
                </div>
                <div class="col-6">
                    <div class="small text-muted">ODP Terpilih:</div>
                    <strong class="text-dark small" id="dt_odp_name">-</strong>
                </div>
                <div class="col-6">
                    <div class="small text-muted">Surveyor:</div>
                    <span class="text-dark small" id="dt_surveyor_name">-</span>
                </div>
                <div class="col-6">
                    <div class="small text-muted">Waktu Survei:</div>
                    <span class="text-dark small" id="dt_created_at">-</span>
                </div>
            </div>

            <div class="mt-2" id="dt_result_note_wrapper">
                <div class="small text-muted">Catatan Hasil:</div>
                <div class="p-2 rounded bg-light small text-dark border text-break" id="dt_result_note">-</div>
            </div>
        </div>

        {{-- Section 3: Material --}}
        <div class="border rounded p-3 mb-3">
            <h6 class="fw-bold mb-2 text-dark border-bottom pb-2">
                Daftar Kebutuhan Material
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Barang</th>
                            <th class="text-end">Jumlah</th>
                            <th>Satuan</th>
                        </tr>
                    </thead>
                    <tbody id="dt_materials_tbody">
                        <tr><td colspan="3" class="text-center text-muted py-2">Tidak ada material yang diajukan</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Section 4: Riwayat Review --}}
        <div class="border rounded p-3 mb-3" id="dt_review_wrapper">
            <h6 class="fw-bold mb-2 text-dark border-bottom pb-2">
                Peninjauan Admin
            </h6>
            <div class="small text-muted mb-1">
                Peninjau: <strong class="text-dark" id="dt_reviewer_name">-</strong> (<span id="dt_reviewed_at">-</span>)
            </div>
            <div class="small text-muted">Catatan Peninjauan:</div>
            <div class="p-2 rounded bg-light small text-dark border text-break" id="dt_review_note">-</div>
        </div>

    <x-slot:footer>
        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
            <x-btn variant="neutral" data-bs-dismiss="offcanvas">Tutup</x-btn>
            <div class="d-flex gap-2">
                <x-btn variant="danger" class="d-none" id="btn-cancel-customer">
                    Batalkan Pelanggan
                </x-btn>
                <x-btn variant="warning" class="d-none" id="btn-request-resurvey">
                    Minta Survei Ulang
                </x-btn>
                <x-btn variant="approve" class="d-none" id="btn-assign-technician">
                    Tugaskan Teknisi
                </x-btn>
            </div>
        </div>
    </x-slot:footer>
</x-offcanvas-form>

{{-- Offcanvas Tugaskan Teknisi --}}
<x-offcanvas-form id="assignTechnicianOffcanvas" formId="assignTechnicianForm" title="Tugaskan Teknisi" subtitle="Pilih teknisi lapangan aktif untuk instalasi dan aktivasi.">
    <input type="hidden" name="survey_id" id="assign_survey_id">

    <div class="alert alert-light border mb-3">
        <div class="small text-muted">Pelanggan</div>
        <div class="fw-bold text-dark " id="assign_customer_display">-</div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Pilih Teknisi <span class="text-danger">*</span></label>
        <select name="technician_user_id" id="assign_technician_id" class="form-select form-select-sm" required>
            <option value="">-- Pilih Teknisi Aktif --</option>
            @foreach($technicians as $tech)
                <option value="{{ $tech->id }}" data-pop="{{ $tech->pop_id }}">{{ $tech->name }} ({{ $tech->phone ?? 'Tanpa No. HP' }})</option>
            @endforeach
        </select>
        <div class="invalid-feedback"></div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Tanggal Rencana Pemasangan</label>
        <input type="date" name="scheduled_date" id="assign_scheduled_date" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}">
        <div class="invalid-feedback"></div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Catatan untuk Teknisi</label>
        <textarea name="admin_note" id="assign_admin_note" class="form-control form-control-sm" rows="3" placeholder="Instruksi khusus, patokan rumah, atau waktu luang pelanggan..."></textarea>
        <div class="invalid-feedback"></div>
    </div>
</x-offcanvas-form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isSuperAdmin = {{ $isSuperAdmin ? 'true' : 'false' }};
    let currentSurveyId = null;

    const columns = [
        { data: 'customer_code', name: 'customer_code', render: d => `<span class="badge bg-light text-dark border">${window.escapeHtml(d)}</span>` },
        { data: 'customer_name', name: 'customer_name', className: 'fw-semibold text-dark' },
    ];

    if (isSuperAdmin) {
        columns.push({ data: 'pop_name', name: 'pop_name' });
    }

    columns.push(
        { data: 'surveyor_name', name: 'surveyor_name' },
        {
            data: null,
            name: 'odc_name',
            render: (d, t, row) => `<div class="small">${window.escapeHtml(row.odc_name || '-')} / <span class="text-muted">${window.escapeHtml(row.odp_name || '-')}</span></div>`
        },
        {
            data: 'result',
            name: 'result',
            render: (d, t, row) => window.statusBadge('survey_result', d || row.result_label)
        },
        {
            data: 'status',
            name: 'status',
            render: (d, t, row) => window.statusBadge('survey_status', d || row.status_label)
        },
        { data: 'created_at', name: 'created_at', className: 'small text-muted' },
        {
            data: 'id',
            orderable: false,
            searchable: false,
            className: 'text-end',
            render: id => window.btnIconHtml('view', {
                class: 'btn-detail',
                'data-id': id,
                title: 'Lihat Detail'
            })
        }
    );

    const table = window.initServerTable('#surveysTable', {
        ajaxUrl: '{{ route('surveys.index') }}',
        filterForm: '#filter-surveys-form',
        resetButton: '#btn-reset-surveys-filter',
        columns: columns
    });

    // Detail Action
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-detail');
        if (!btn) return;

        const id = btn.getAttribute('data-id');
        currentSurveyId = id;

        try {
            const res = await window.apiRequest(`{{ url('surveys') }}/${id}`, { method: 'GET' });
            const s = res.survey;

            document.getElementById('detailSurveySubtitle').textContent = `${s.customer_code} - ${s.customer_name}`;
            document.getElementById('dt_customer_code').textContent = s.customer_code;
            document.getElementById('dt_customer_name').textContent = s.customer_name;
            document.getElementById('dt_customer_address').textContent = s.customer_address || '-';
            document.getElementById('dt_customer_phone').textContent = s.customer_phone || '-';
            document.getElementById('dt_coordinates').textContent = s.coordinates || '-';
            document.getElementById('dt_route_btn').href = s.route_url;

            const resBadgeContainer = document.getElementById('dt_result_badge');
            resBadgeContainer.innerHTML = window.statusBadge('survey_result', s.result || s.result_label);

            const stBadgeContainer = document.getElementById('dt_status_badge');
            stBadgeContainer.innerHTML = window.statusBadge('survey_status', s.status || s.status_label);

            document.getElementById('dt_odc_name').textContent = s.odc_name;
            document.getElementById('dt_odp_name').textContent = s.odp_name;
            document.getElementById('dt_surveyor_name').textContent = s.surveyor_name;
            document.getElementById('dt_created_at').textContent = s.created_at;
            document.getElementById('dt_result_note').textContent = s.result_note;

            // Materials Table
            const tbody = document.getElementById('dt_materials_tbody');
            tbody.innerHTML = '';
            if (s.items && s.items.length > 0) {
                s.items.forEach(it => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="fw-medium">${window.escapeHtml(it.material_name)}</td>
                        <td class="text-end fw-bold">${it.quantity}</td>
                        <td>${window.escapeHtml(it.unit)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2">Tidak ada material yang diajukan</td></tr>';
            }

            // Review Info
            const reviewWrapper = document.getElementById('dt_review_wrapper');
            if (s.reviewer_name && s.reviewer_name !== '-') {
                document.getElementById('dt_reviewer_name').textContent = s.reviewer_name;
                document.getElementById('dt_reviewed_at').textContent = s.reviewed_at;
                document.getElementById('dt_review_note').textContent = s.review_note;
                reviewWrapper.style.display = 'block';
            } else {
                reviewWrapper.style.display = 'none';
            }

            // Action Buttons
            const btnResurvey = document.getElementById('btn-request-resurvey');
            const btnCancel = document.getElementById('btn-cancel-customer');
            const btnAssign = document.getElementById('btn-assign-technician');

            btnResurvey.classList.toggle('d-none', !s.can_review);
            btnCancel.classList.toggle('d-none', !s.can_cancel);
            btnAssign.classList.toggle('d-none', !s.can_assign);

            btnAssign.onclick = function () {
                if (window.closeOffcanvas) {
                    window.closeOffcanvas('detailSurveyOffcanvas');
                }

                document.getElementById('assign_survey_id').value = s.id;
                document.getElementById('assign_customer_display').textContent = `${s.customer_code} - ${s.customer_name} (${s.pop_name})`;

                const techSelect = document.getElementById('assign_technician_id');
                Array.from(techSelect.options).forEach(opt => {
                    if (!opt.value) return;
                    const pop = opt.getAttribute('data-pop');
                    const match = isSuperAdmin ? (String(pop) === String(s.pop_id)) : true;
                    opt.style.display = match ? '' : 'none';
                    opt.disabled = !match;
                });
                techSelect.value = '';

                setTimeout(() => {
                    if (window.openOffcanvas) {
                        window.openOffcanvas('assignTechnicianOffcanvas');
                    }
                }, 350);
            };

            if (window.openOffcanvas) {
                window.openOffcanvas('detailSurveyOffcanvas');
            }
        } catch (err) {
            if (typeof window.toast === 'function') {
                window.toast('error', err.message || 'Gagal memuat detail survei.');
            }
        }
    });

    // Action: Minta Survei Ulang
    document.getElementById('btn-request-resurvey')?.addEventListener('click', async function () {
        if (!currentSurveyId) return;

        let note = null;
        if (typeof window.promptReason === 'function') {
            const promptRes = await window.promptReason({
                title: 'Minta Survei Ulang',
                inputLabel: 'Catatan Peninjauan / Alasan Survei Ulang',
                inputPlaceholder: 'Tuliskan catatan teknis untuk surveyor (misal: titik ODP kurang jelas, cek ulang redaman)...',
                confirmButtonText: 'Kirim Permintaan'
            });
            note = (promptRes && typeof promptRes === 'object') ? (promptRes.isConfirmed ? promptRes.value : null) : promptRes;
        }

        if (!note || !note.trim()) return;

        try {
            const res = await window.apiRequest(`{{ url('surveys') }}/${currentSurveyId}/request-resurvey`, {
                method: 'POST',
                body: JSON.stringify({ review_note: note.trim() })
            });

            if (window.closeOffcanvas) {
                window.closeOffcanvas('detailSurveyOffcanvas');
            }
            if (typeof window.toast === 'function') {
                window.toast('success', res.message || 'Permintaan survei ulang berhasil dikirim.');
            }
            if (table) table.ajax.reload(null, false);
        } catch (err) {
            if (typeof window.toast === 'function') {
                window.toast('error', err.message || 'Gagal mengirim permintaan survei ulang.');
            }
        }
    });

    // Action: Batalkan Pelanggan
    document.getElementById('btn-cancel-customer')?.addEventListener('click', async function () {
        if (!currentSurveyId) return;

        let reason = null;
        if (typeof window.promptReason === 'function') {
            const promptRes = await window.promptReason({
                title: 'Batalkan Pelanggan',
                inputLabel: 'Alasan Pembatalan Pelanggan',
                inputPlaceholder: 'Tuliskan alasan pembatalan (misal: pelanggan batal pasang, di luar jangkauan jaringan)...',
                confirmButtonText: 'Batalkan Pelanggan',
                confirmButtonClass: 'btn btn-danger'
            });
            reason = (promptRes && typeof promptRes === 'object') ? (promptRes.isConfirmed ? promptRes.value : null) : promptRes;
        }

        if (!reason || !reason.trim()) return;

        try {
            const res = await window.apiRequest(`{{ url('surveys') }}/${currentSurveyId}/cancel-customer`, {
                method: 'POST',
                body: JSON.stringify({ cancel_reason: reason.trim() })
            });

            if (window.closeOffcanvas) {
                window.closeOffcanvas('detailSurveyOffcanvas');
            }
            if (typeof window.toast === 'function') {
                window.toast('success', res.message || 'Pelanggan berhasil dibatalkan.');
            }
            if (table) table.ajax.reload(null, false);
        } catch (err) {
            if (typeof window.toast === 'function') {
                window.toast('error', err.message || 'Gagal membatalkan pelanggan.');
            }
        }
    });

    // Submit Assign Technician Form
    const assignForm = document.getElementById('assignTechnicianForm');
    assignForm?.addEventListener('submit', async function (e) {
        e.preventDefault();

        assignForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        assignForm.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');

        const surveyId = document.getElementById('assign_survey_id').value;
        const formData = new FormData(assignForm);

        try {
            const res = await window.apiRequest(`{{ url('surveys') }}/${surveyId}/assign-technician`, {
                method: 'POST',
                body: JSON.stringify({
                    technician_user_id: formData.get('technician_user_id'),
                    scheduled_date: formData.get('scheduled_date'),
                    admin_note: formData.get('admin_note')
                })
            });

            if (window.closeOffcanvas) {
                window.closeOffcanvas('assignTechnicianOffcanvas');
            }
            if (typeof window.toast === 'function') {
                window.toast('success', res.message || 'Teknisi berhasil ditugaskan.');
            }
            if (table) table.ajax.reload(null, false);
        } catch (err) {
            if (err.status === 422 && err.data && err.data.errors) {
                const errors = err.data.errors;
                Object.keys(errors).forEach(key => {
                    const el = assignForm.querySelector(`[name="${key}"]`);
                    if (el) {
                        el.classList.add('is-invalid');
                        if (el.nextElementSibling && el.nextElementSibling.classList.contains('invalid-feedback')) {
                            el.nextElementSibling.textContent = errors[key][0];
                        }
                    }
                });
                if (typeof window.toast === 'function') {
                    window.toast('warning', err.data.message || 'Periksa kembali data penugasan.');
                }
            } else {
                if (typeof window.toast === 'function') {
                    window.toast('error', err.message || 'Gagal menugaskan teknisi.');
                }
            }
        }
    });

    // Ekspor Laporan Pemasangan
    document.getElementById('btn-export-installation')?.addEventListener('click', function (e) {
        e.preventDefault();
        const form = document.getElementById('filter-surveys-form');
        const params = new URLSearchParams();
        if (form) {
            new FormData(form).forEach((val, key) => {
                if (val) {
                    params.set(key, val);
                    if (key === 'from') params.set('date_from', val);
                    if (key === 'to') params.set('date_to', val);
                }
            });
        }
        window.location.href = '{{ route('export.installation-report') }}' + (params.toString() ? '?' + params.toString() : '');
    });
});
</script>
@endpush
@endsection
