@extends('layouts.app')

@section('title', 'Penugasan Teknisi')

@section('content')
<x-page-header title="Penugasan Teknisi" subtitle="Kelola penugasan instalasi fisik, dokumentasi lapangan, dan verifikasi aktivasi pelanggan.">
    <x-btn id="btn-export-installation-wo" variant="export" href="{{ route('export.installation-report') }}" title="Ekspor laporan pemasangan sesuai filter">
        Ekspor Laporan
    </x-btn>
</x-page-header>

<!-- {{-- Stat Cards --}}
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-7 g-3 mb-3">
    <div class="col">
        <x-stat-card border-color="primary" title="Total Tugas" value="{{ number_format($stats['total']) }}" color="primary" />
    </div>
    <div class="col">
        <x-stat-card border-color="warning" title="Permintaan Perbaikan" value="{{ number_format($stats['requested']) }}" color="warning" />
    </div>
    <div class="col">
        <x-stat-card border-color="info" title="Ditugaskan" value="{{ number_format($stats['assigned']) }}" color="info" />
    </div>
    <div class="col">
        <x-stat-card border-color="primary" title="Dikerjakan" value="{{ number_format($stats['in_progress']) }}" color="primary" />
    </div>
    <div class="col">
        <x-stat-card border-color="warning" title="Menunggu Verifikasi" value="{{ number_format($stats['submitted']) }}" color="warning" />
    </div>
    <div class="col">
        <x-stat-card border-color="danger" title="Perlu Perbaikan" value="{{ number_format($stats['needs_revision']) }}" color="danger" />
    </div>
    <div class="col">
        <x-stat-card border-color="success" title="Terverifikasi" value="{{ number_format($stats['verified']) }}" color="success" />
    </div>
</div> -->
{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    {{-- Total Tugas --}}
    <div class="col-12 col-md-3">
        <x-stat-card
            border-color="#2563eb"
            title="Total Tugas"
            value="{{ number_format($stats['total']) }}"
            color="primary"
        />
    </div>
    {{-- Status Tugas: 3 Kolom x 2 Baris --}}
    <div class="col-12 col-md-9">
        <div class="row row-cols-2 row-cols-md-3 g-3">
            <div class="col">
                <x-stat-card border-color="#d97706" title="Permintaan Perbaikan"
                    value="{{ number_format($stats['requested']) }}" color="warning" />
            </div>
            <div class="col">
                <x-stat-card border-color="#0891b2" title="Ditugaskan"
                    value="{{ number_format($stats['assigned']) }}" color="info" />
            </div>
            <div class="col">
                <x-stat-card border-color="#2563eb" title="Dikerjakan"
                    value="{{ number_format($stats['in_progress']) }}" color="primary" />
            </div>
            <div class="col">
                <x-stat-card border-color="#64748b" title="Menunggu Verifikasi"
                    value="{{ number_format($stats['submitted']) }}" color="secondary" />
            </div>
            <div class="col">
                <x-stat-card border-color="#dc2626" title="Perlu Perbaikan"
                    value="{{ number_format($stats['needs_revision']) }}" color="danger" />
            </div>
            <div class="col">
                <x-stat-card border-color="#16a34a" title="Terverifikasi"
                    value="{{ number_format($stats['verified']) }}" color="success" />
            </div>
        </div>
    </div>
</div>

{{-- Filter Card --}}
<x-filter-card>
    <form id="filter-work-orders-form" class="row g-2 align-items-end">
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
            <label class="form-label small fw-semibold">Jenis Penugasan</label>
            <select name="type" id="filter_type" class="form-select form-select-sm">
                <option value="">-- Semua Jenis --</option>
                <option value="INSTALLATION">Instalasi Baru</option>
                <option value="REPAIR">Perbaikan (Repair)</option>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Status</label>
            <select name="status" id="filter_status" class="form-select form-select-sm">
                <option value="">-- Semua Status --</option>
                @foreach(\App\Enums\WorkOrderStatus::cases() as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Teknisi</label>
            <select name="technician_id" id="filter_technician_id" class="form-select form-select-sm">
                <option value="">-- Semua Teknisi --</option>
                @foreach($technicians as $tech)
                    <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Tanggal Rencana</label>
            <input type="date" name="scheduled_date" id="filter_scheduled_date" class="form-control form-control-sm">
        </div>
        <div class="col-12 col-md-auto ms-auto d-flex align-items-end">
            <x-btn id="btn-reset-wo-filter" variant="neutral" type="reset" class="btn-sm w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- Table Card --}}
<x-table-card id="work-orders-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 w-100" id="workOrdersTable">
            <thead class="table-light">
                <tr>
                    <th>Kode Pelanggan</th>
                    <th>Nama Pelanggan</th>
                    @if($isSuperAdmin)
                        <th>POP</th>
                    @endif
                    <th>Teknisi</th>
                    <th>Rencana</th>
                    <th>Status</th>
                    <th>Foto</th>
                    <th>Selesai</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</x-table-card>

{{-- Offcanvas Detail Penugasan & Verifikasi --}}
<x-offcanvas-form
    id="detailWorkOrderOffcanvas"
    title="Detail Penugasan Teknisi"
    titleId="detailWoTitle"
    subtitle="-"
    subtitleId="detailWoSubtitle">
        {{-- Section 1: Ringkasan Pelanggan & Lokasi --}}
        <div class="card border-0 bg-light mb-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="badge bg-secondary me-1" id="wo_customer_code">-</span>
                        <strong class="text-dark" id="wo_customer_name">-</strong>
                    </div>
                    <x-btn-icon action="route" href="#" target="_blank" rel="noopener noreferrer" id="wo_route_btn" title="Buka Rute Maps" />
                </div>
                <div class="small text-muted mb-1"><strong>Alamat:</strong> <span id="wo_customer_address" class="text-dark">-</span></div>
                <div class="small text-muted mb-1"><strong>Telepon:</strong> <span id="wo_customer_phone" class="text-dark">-</span></div>
                <div class="small text-muted mb-1"><strong>ODC / ODP:</strong> <span id="wo_odc_odp" class="text-dark">-</span></div>
                <div class="small text-muted"><strong>Koordinat:</strong> <span id="wo_coordinates" class="text-dark">-</span></div>
            </div>
        </div>

        {{-- Section 2: Data Penugasan --}}
        <div class="border rounded p-3 mb-3">
            <h6 class="fw-bold mb-3 text-dark border-bottom pb-2">
                Data Penugasan &amp; Teknisi
            </h6>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <div class="small text-muted">Status Tugas:</div>
                    <span id="wo_status_badge">-</span>
                </div>
                <div class="col-6">
                    <div class="small text-muted">Teknisi Bertugas:</div>
                    <strong class="text-dark small" id="wo_technician_name">-</strong>
                    <div class="text-muted small" id="wo_technician_phone">-</div>
                </div>
                <div class="col-6">
                    <div class="small text-muted">Rencana Pemasangan:</div>
                    <span class="text-dark small" id="wo_scheduled_date">-</span>
                </div>
                <div class="col-6">
                    <div class="small text-muted">Tanggal Selesai:</div>
                    <span class="text-dark small" id="wo_completed_on">-</span>
                </div>
            </div>

            <div class="mt-2">
                <div class="small text-muted">Catatan Admin untuk Teknisi:</div>
                <div class="p-2 rounded bg-light small text-dark border text-break" id="wo_admin_note">-</div>
            </div>

            <div class="mt-2">
                <div class="small text-muted">Catatan Hasil Pengerjaan Teknisi:</div>
                <div class="p-2 rounded bg-light small text-dark border text-break" id="wo_technician_notes">-</div>
            </div>

            <div class="mt-2 d-none" id="wo_review_note_wrapper">
                <div class="small text-muted">Catatan Perbaikan/Pembatalan:</div>
                <div class="p-2 rounded bg-warning bg-opacity-10 small text-dark border border-warning text-break" id="wo_review_note">-</div>
            </div>
        </div>

        {{-- Section 3: Material Survei --}}
        <div class="border rounded p-3 mb-3">
            <h6 class="fw-bold mb-2 text-dark border-bottom pb-2">
                Rincian Material (Hasil Survei)
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Barang</th>
                            <th class="text-end">Jumlah</th>
                            <th>Satuan</th>
                        </tr>
                    </thead>
                    <tbody id="wo_materials_tbody">
                        <tr><td colspan="3" class="text-center text-muted py-2">-</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Section 4: Galeri Foto Dokumentasi Lapangan --}}
        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                <h6 class="fw-bold mb-0 text-dark">
                    Foto Dokumentasi Pemasangan
                </h6>
                <span class="badge bg-light text-dark border" id="wo_photo_counter">0 Foto</span>
            </div>

            <div class="row row-cols-2 row-cols-md-3 g-2" id="wo_photos_gallery">
            <div class="row row-cols-2 g-2" id="wo_photos_gallery">
                <div class="col-12 text-center text-muted py-3">Tidak ada foto terunggah.</div>
            </div>
        </div>

    <x-slot:footer>
        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
            <x-btn variant="neutral" data-bs-dismiss="offcanvas">Tutup</x-btn>
            <div class="d-flex gap-2 flex-wrap">
                <x-btn variant="danger" class="d-none" id="btn-reject-repair-wo">
                    Tolak Permintaan
                </x-btn>
                <x-btn variant="approve" class="d-none" id="btn-assign-repair-wo">
                    Tugaskan Teknisi
                </x-btn>
                <x-btn variant="danger" class="d-none" id="btn-cancel-wo">
                    Batalkan Penugasan
                </x-btn>
                <x-btn variant="warning" class="d-none" id="btn-reassign-wo">
                    Alihkan Teknisi
                </x-btn>
                <x-btn variant="warning" class="d-none" id="btn-revision-wo">
                    Minta Perbaikan
                </x-btn>
                <x-btn variant="approve" class="d-none" id="btn-verify-wo">
                    Verifikasi &amp; Selesai
                </x-btn>
            </div>
        </div>
    </x-slot:footer>
</x-offcanvas-form>

{{-- Offcanvas Alihkan Teknisi --}}
<x-offcanvas-form id="reassignTechnicianOffcanvas" formId="reassignTechnicianForm" title="Alihkan Penugasan Teknisi" subtitle="Pilih teknisi pengganti untuk tugas pemasangan ini.">
    <input type="hidden" name="work_order_id" id="reassign_wo_id">

    <div class="alert alert-light border mb-3">
        <div class="small text-muted">Pelanggan</div>
        <div class="fw-bold text-dark " id="reassign_customer_display">-</div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Pilih Teknisi Pengganti <span class="text-danger">*</span></label>
        <select name="technician_user_id" id="reassign_technician_id" class="form-select form-select-sm" required>
            <option value="">-- Pilih Teknisi --</option>
            @foreach($technicians as $tech)
                <option value="{{ $tech->id }}" data-pop="{{ $tech->pop_id }}">{{ $tech->name }} ({{ $tech->phone ?? 'Tanpa No. HP' }})</option>
            @endforeach
        </select>
        <div class="invalid-feedback"></div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Tanggal Rencana Baru</label>
        <input type="date" name="scheduled_date" id="reassign_scheduled_date" class="form-control form-control-sm">
        <div class="invalid-feedback"></div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Catatan Tambahan</label>
        <textarea name="admin_note" id="reassign_admin_note" class="form-control form-control-sm" rows="3" placeholder="Instruksi pengalihan teknisi..."></textarea>
        <div class="invalid-feedback"></div>
    </div>
</x-offcanvas-form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isSuperAdmin = {{ $isSuperAdmin ? 'true' : 'false' }};
    let currentWoId = null;

    const columns = [
        { data: 'customer_code', name: 'customer_code', render: d => `<span class="badge bg-light text-dark border">${window.escapeHtml(d)}</span>` },
        { data: 'customer_name', name: 'customer_name', className: 'fw-semibold text-dark' },
    ];

    if (isSuperAdmin) {
        columns.push({ data: 'pop_name', name: 'pop_name' });
    }

    columns.push(
        { data: 'technician_name', name: 'technician_name' },
        { data: 'scheduled_date', name: 'scheduled_date' },
        {
            data: 'status',
            name: 'status',
            render: (d, t, row) => window.statusBadge('work_order_status', d || row.status_label)
        },
        {
            data: 'photos_count',
            name: 'photos_count',
            className: 'text-center',
            render: count => `<span class="badge ${count >= 2 ? 'bg-success' : 'bg-secondary'}">${count} foto</span>`
        },
        { data: 'completed_on', name: 'completed_on', className: 'small text-muted' },
        {
            data: 'id',
            orderable: false,
            searchable: false,
            className: 'text-end',
            render: id => window.btnIconHtml('view', {
                class: 'btn-detail-wo',
                'data-id': id,
                title: 'Lihat Detail Penugasan'
            })
        }
    );

    const table = window.initServerTable('#workOrdersTable', {
        ajaxUrl: '{{ route('work-orders.index') }}',
        filterForm: '#filter-work-orders-form',
        resetButton: '#btn-reset-wo-filter',
        columns: columns
    });

    // Detail Action
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-detail-wo');
        if (!btn) return;

        const id = btn.getAttribute('data-id');
        currentWoId = id;

        try {
            const res = await window.apiRequest(`{{ url('work-orders') }}/${id}`, { method: 'GET' });
            const wo = res.work_order;

            document.getElementById('detailWoSubtitle').textContent = `${wo.customer_code} - ${wo.customer_name}`;
            document.getElementById('wo_customer_code').textContent = wo.customer_code;
            document.getElementById('wo_customer_name').textContent = wo.customer_name;
            document.getElementById('wo_customer_address').textContent = wo.customer_address || '-';
            document.getElementById('wo_customer_phone').textContent = wo.customer_phone || '-';
            document.getElementById('wo_odc_odp').textContent = `${wo.odc_name} / ${wo.odp_name}`;
            document.getElementById('wo_coordinates').textContent = wo.coordinates || '-';
            document.getElementById('wo_route_btn').href = wo.route_url;

            const stBadgeContainer = document.getElementById('wo_status_badge');
            stBadgeContainer.innerHTML = window.statusBadge('work_order_status', wo.status || wo.status_label);

            document.getElementById('wo_technician_name').textContent = wo.technician_name;
            document.getElementById('wo_technician_phone').textContent = wo.technician_phone;
            document.getElementById('wo_scheduled_date').textContent = wo.scheduled_date;
            document.getElementById('wo_completed_on').textContent = wo.completed_on;
            document.getElementById('wo_admin_note').textContent = wo.admin_note;
            document.getElementById('wo_technician_notes').textContent = wo.technician_notes;

            const reviewWrapper = document.getElementById('wo_review_note_wrapper');
            if (wo.review_note && wo.review_note !== '-') {
                document.getElementById('wo_review_note').textContent = wo.review_note;
                reviewWrapper.style.display = 'block';
            } else {
                reviewWrapper.style.display = 'none';
            }

            // Material
            const tbody = document.getElementById('wo_materials_tbody');
            tbody.innerHTML = '';
            if (wo.survey_materials && wo.survey_materials.length > 0) {
                wo.survey_materials.forEach(it => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${window.escapeHtml(it.material_name)}</td>
                        <td class="text-end fw-bold">${it.quantity}</td>
                        <td>${window.escapeHtml(it.unit)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2">Tidak ada material yang diajukan</td></tr>';
            }

            // Foto
            const gallery = document.getElementById('wo_photos_gallery');
            document.getElementById('wo_photo_counter').textContent = `${wo.photos.length} Foto (Min. 2 Foto)`;
            gallery.innerHTML = '';
            if (wo.photos.length === 0) {
                gallery.innerHTML = '<div class="col-12 text-center text-muted py-3">Tidak ada foto dokumentasi terunggah.</div>';
            } else {
                wo.photos.forEach(p => {
                    const col = document.createElement('div');
                    col.className = 'col';
                    const stageBadge = window.statusBadge('photo_stage', p.stage) || `<span class="badge bg-secondary mb-1">${window.escapeHtml(p.stage_label)}</span>`;
                    col.innerHTML = `
                        <div class="card h-100 border shadow-sm">
                            <a href="${p.url}" target="_blank" rel="noopener noreferrer">
                                <img src="${p.url}" class="card-img-top img-thumb-120" alt="Foto"
                                     onerror="window.handleImageError(this)">
                            </a>
                            <div class="p-2 small">
                                ${stageBadge}
                                <div class="text-truncate text-muted" title="${window.escapeHtml(p.caption)}">${window.escapeHtml(p.caption)}</div>
                            </div>
                        </div>
                    `;
                    gallery.appendChild(col);
                });
            }

            // Tombol Aksi
            const btnAssignRepair = document.getElementById('btn-assign-repair-wo');
            const btnRejectRepair = document.getElementById('btn-reject-repair-wo');
            const btnVerify = document.getElementById('btn-verify-wo');
            const btnRevision = document.getElementById('btn-revision-wo');
            const btnReassign = document.getElementById('btn-reassign-wo');
            const btnCancel = document.getElementById('btn-cancel-wo');

            if (btnAssignRepair) btnAssignRepair.classList.toggle('d-none', !wo.can_assign_repair);
            if (btnRejectRepair) btnRejectRepair.classList.toggle('d-none', !wo.can_reject_repair);
            btnVerify.classList.toggle('d-none', !wo.can_verify);
            btnRevision.classList.toggle('d-none', !wo.can_request_revision);
            btnReassign.classList.toggle('d-none', !wo.can_reassign);
            btnCancel.classList.toggle('d-none', !wo.can_cancel);

            if (btnAssignRepair) {
                btnAssignRepair.onclick = function () {
                    if (window.closeOffcanvas) window.closeOffcanvas('detailWorkOrderOffcanvas');
                    const woInput = document.getElementById('reassign_wo_id');
                    woInput.value = wo.id;
                    woInput.setAttribute('data-action', 'assign-repair');
                    document.getElementById('reassign_customer_display').textContent = `${wo.customer_code} - ${wo.customer_name}`;
                    document.getElementById('reassign_scheduled_date').value = wo.scheduled_date_raw || '';
                    document.getElementById('reassign_admin_note').value = wo.admin_note !== '-' ? wo.admin_note : '';

                    const reassignSelect = document.getElementById('reassign_technician_id');
                    Array.from(reassignSelect.options).forEach(opt => {
                        if (!opt.value) return;
                        const pop = opt.getAttribute('data-pop');
                        const match = isSuperAdmin ? (String(pop) === String(wo.pop_id)) : true;
                        opt.style.display = match ? '' : 'none';
                        opt.disabled = !match;
                    });
                    reassignSelect.value = '';

                    setTimeout(() => {
                        if (window.openOffcanvas) window.openOffcanvas('reassignTechnicianOffcanvas');
                    }, 350);
                };
            }

            if (btnRejectRepair) {
                btnRejectRepair.onclick = async function () {
                    let reason = null;
                    if (typeof window.promptReason === 'function') {
                        const promptRes = await window.promptReason({
                            title: 'Tolak Permintaan Perbaikan',
                            inputLabel: 'Alasan Penolakan Permintaan Perbaikan',
                            inputPlaceholder: 'Tuliskan alasan penolakan teknis/operasional...',
                            confirmButtonText: 'Tolak Permintaan',
                            confirmButtonClass: 'btn btn-danger'
                        });
                        reason = (promptRes && typeof promptRes === 'object') ? (promptRes.isConfirmed ? promptRes.value : null) : promptRes;
                    }
                    if (!reason || !reason.trim()) return;

                    try {
                        const res = await window.apiRequest(`{{ url('work-orders') }}/${currentWoId}/reject-repair`, {
                            method: 'POST',
                            body: JSON.stringify({ reason: reason.trim() })
                        });
                        if (window.closeOffcanvas) window.closeOffcanvas('detailWorkOrderOffcanvas');
                        if (typeof window.toast === 'function') window.toast('success', res.message || 'Permintaan perbaikan berhasil ditolak.');
                        if (table) table.ajax.reload(null, false);
                    } catch (err) {
                        if (typeof window.toast === 'function') window.toast('error', err.message || 'Gagal menolak permintaan perbaikan.');
                    }
                };
            }

            btnReassign.onclick = function () {
                if (window.closeOffcanvas) window.closeOffcanvas('detailWorkOrderOffcanvas');
                const woInput = document.getElementById('reassign_wo_id');
                woInput.value = wo.id;
                woInput.removeAttribute('data-action');
                document.getElementById('reassign_customer_display').textContent = `${wo.customer_code} - ${wo.customer_name}`;
                document.getElementById('reassign_scheduled_date').value = wo.scheduled_date_raw || '';
                document.getElementById('reassign_admin_note').value = wo.admin_note !== '-' ? wo.admin_note : '';

                const reassignSelect = document.getElementById('reassign_technician_id');
                Array.from(reassignSelect.options).forEach(opt => {
                    if (!opt.value) return;
                    const pop = opt.getAttribute('data-pop');
                    const match = isSuperAdmin ? (String(pop) === String(wo.pop_id)) : true;
                    opt.style.display = match ? '' : 'none';
                    opt.disabled = !match;
                });
                reassignSelect.value = wo.technician_id || '';

                setTimeout(() => {
                    if (window.openOffcanvas) window.openOffcanvas('reassignTechnicianOffcanvas');
                }, 350);
            };

            if (window.openOffcanvas) {
                window.openOffcanvas('detailWorkOrderOffcanvas');
            }
        } catch (err) {
            if (typeof window.toast === 'function') {
                window.toast('error', err.message || 'Gagal memuat detail tugas.');
            }
        }
    });

    // Action: Verifikasi & Aktifkan Pelanggan
    document.getElementById('btn-verify-wo')?.addEventListener('click', async function () {
        if (!currentWoId) return;

        let confirmed = true;
        if (typeof window.confirmDialog === 'function') {
            confirmed = await window.confirmDialog({
                title: 'Verifikasi & Aktifkan Pelanggan?',
                text: 'Status pelanggan akan diubah menjadi AKTIF dan fee marketing (jika berlaku) akan diproses.',
                confirmButtonText: 'Ya, Verifikasi & Aktifkan'
            });
        }
        if (!confirmed) return;

        try {
            const res = await window.apiRequest(`{{ url('work-orders') }}/${currentWoId}/verify`, {
                method: 'POST',
                body: JSON.stringify({})
            });

            if (window.closeOffcanvas) {
                window.closeOffcanvas('detailWorkOrderOffcanvas');
            }

            if (typeof window.toast === 'function') {
                window.toast('success', `Pelanggan ${res.customer_code} telah AKTIF. Silakan buka detail pelanggan untuk membuat kode aktivasi akun.`);
            }

            if (table) table.ajax.reload(null, false);
        } catch (err) {
            if (typeof window.toast === 'function') {
                window.toast('error', err.message || 'Gagal memverifikasi tugas.');
            }
        }
    });

    // Action: Minta Perbaikan
    document.getElementById('btn-revision-wo')?.addEventListener('click', async function () {
        if (!currentWoId) return;

        let note = null;
        if (typeof window.promptReason === 'function') {
            const promptRes = await window.promptReason({
                title: 'Minta Perbaikan Pekerjaan',
                inputLabel: 'Catatan Perbaikan untuk Teknisi',
                inputPlaceholder: 'Tuliskan poin perbaikan (misal: foto ODP belum jelas, redaman terlalu tinggi)...',
                confirmButtonText: 'Kirim Permintaan'
            });
            note = (promptRes && typeof promptRes === 'object') ? (promptRes.isConfirmed ? promptRes.value : null) : promptRes;
        }

        if (!note || !note.trim()) return;

        try {
            const res = await window.apiRequest(`{{ url('work-orders') }}/${currentWoId}/request-revision`, {
                method: 'POST',
                body: JSON.stringify({ review_note: note.trim() })
            });

            if (window.closeOffcanvas) {
                window.closeOffcanvas('detailWorkOrderOffcanvas');
            }
            if (typeof window.toast === 'function') {
                window.toast('success', res.message || 'Permintaan perbaikan berhasil dikirim ke teknisi.');
            }
            if (table) table.ajax.reload(null, false);
        } catch (err) {
            if (typeof window.toast === 'function') {
                window.toast('error', err.message || 'Gagal meminta perbaikan.');
            }
        }
    });

    // Action: Batalkan Penugasan
    document.getElementById('btn-cancel-wo')?.addEventListener('click', async function () {
        if (!currentWoId) return;

        let reason = null;
        if (typeof window.promptReason === 'function') {
            const promptRes = await window.promptReason({
                title: 'Batalkan Penugasan Teknisi',
                inputLabel: 'Alasan Pembatalan Penugasan',
                inputPlaceholder: 'Tuliskan alasan pembatalan penugasan...',
                confirmButtonText: 'Batalkan Penugasan'
            });
            reason = (promptRes && typeof promptRes === 'object') ? (promptRes.isConfirmed ? promptRes.value : null) : promptRes;
        }

        if (!reason || !reason.trim()) return;

        try {
            const res = await window.apiRequest(`{{ url('work-orders') }}/${currentWoId}/cancel`, {
                method: 'POST',
                body: JSON.stringify({ cancel_reason: reason.trim() })
            });

            if (window.closeOffcanvas) {
                window.closeOffcanvas('detailWorkOrderOffcanvas');
            }
            if (typeof window.toast === 'function') {
                window.toast('success', res.message || 'Penugasan berhasil dibatalkan.');
            }
            if (table) table.ajax.reload(null, false);
        } catch (err) {
            if (typeof window.toast === 'function') {
                window.toast('error', err.message || 'Gagal membatalkan penugasan.');
            }
        }
    });

    // Submit Alihkan Teknisi
    const reassignForm = document.getElementById('reassignTechnicianForm');
    reassignForm?.addEventListener('submit', async function (e) {
        e.preventDefault();

        reassignForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        reassignForm.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');

        const woInput = document.getElementById('reassign_wo_id');
        const woId = woInput.value;
        const actionType = woInput.getAttribute('data-action');
        const endpoint = actionType === 'assign-repair'
            ? `{{ url('work-orders') }}/${woId}/assign-repair`
            : `{{ url('work-orders') }}/${woId}/reassign`;
        const formData = new FormData(reassignForm);

        try {
            const res = await window.apiRequest(endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    technician_user_id: formData.get('technician_user_id'),
                    scheduled_date: formData.get('scheduled_date'),
                    admin_note: formData.get('admin_note')
                })
            });

            woInput.removeAttribute('data-action');

            if (window.closeOffcanvas) {
                window.closeOffcanvas('reassignTechnicianOffcanvas');
            }
            if (typeof window.toast === 'function') {
                window.toast('success', res.message || 'Tugas berhasil diproses.');
            }
            if (table) table.ajax.reload(null, false);
        } catch (err) {
            if (err.status === 422 && err.data && err.data.errors) {
                const errors = err.data.errors;
                Object.keys(errors).forEach(key => {
                    const el = reassignForm.querySelector(`[name="${key}"]`);
                    if (el) {
                        el.classList.add('is-invalid');
                        if (el.nextElementSibling && el.nextElementSibling.classList.contains('invalid-feedback')) {
                            el.nextElementSibling.textContent = errors[key][0];
                        }
                    }
                });
                if (typeof window.toast === 'function') {
                    window.toast('warning', err.data.message || 'Periksa kembali data pengalihan.');
                }
            } else {
                if (typeof window.toast === 'function') {
                    window.toast('error', err.message || 'Gagal mengalihkan penugasan.');
                }
            }
        }
    });

    // Ekspor Laporan Pemasangan
    document.getElementById('btn-export-installation-wo')?.addEventListener('click', function (e) {
        e.preventDefault();
        const form = document.getElementById('filter-work-orders-form');
        const params = new URLSearchParams();
        if (form) {
            new FormData(form).forEach((val, key) => {
                if (val) params.set(key, val);
            });
        }
        window.location.href = '{{ route("export.installation-report") }}' + (params.toString() ? '?' + params.toString() : '');
    });
});
</script>
@endpush
@endsection
