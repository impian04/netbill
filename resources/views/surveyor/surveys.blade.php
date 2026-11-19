@extends('layouts.app')

@section('title', 'Survei Saya')

@section('content')
<x-page-header title="Survei Saya" subtitle="Riwayat dan status hasil survei lapangan yang telah Anda kirimkan.">
    <x-btn href="{{ route('surveyor.queue.index') }}" variant="neutral">Antrean Survei</x-btn>
</x-page-header>

<x-filter-card action="{{ route('surveyor.surveys.index') }}" id="filterSurveysForm" mode="instant">
    <div class="col-12 col-md-4 col-lg-3">
        <label class="form-label small fw-semibold">Status Survei</label>
        <select name="status" id="survey_filter_status" class="form-select form-select-sm">
            <option value="">-- Semua Status --</option>
            @foreach(\App\Enums\SurveyStatus::cases() as $st)
                <option value="{{ $st->value }}" {{ $status === $st->value ? 'selected' : '' }}>
                    {{ $st->label() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-5 col-lg-4">
        <label class="form-label small fw-semibold">Pencarian</label>
        <div class="input-group input-group-sm">
            <input type="search" name="q" id="survey_filter_q" class="form-control form-control-sm" placeholder="Nama, kode, telepon..." value="{{ $search }}">
            <x-btn type="submit" variant="primary">Cari</x-btn>
            <x-btn type="reset" variant="neutral" id="btn-reset-surveys">Reset</x-btn>
        </div>
    </div>
</x-filter-card>

<div class="row" id="surveysCardsWrapper">
    <div class="col-12">
        @if($surveys->isEmpty())
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <h5 class="fw-semibold text-dark">Belum Ada Riwayat Survei</h5>
                    <p class="text-muted mb-0">Anda belum mengirimkan hasil survei lapangan untuk filter ini.</p>
                </div>
            </div>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @foreach($surveys as $s)
                    @php
                        $c = $s->customer;
                        $hasCoord = $s->latitude && $s->longitude;
                        $dest = $hasCoord ? "{$s->latitude},{$s->longitude}" : urlencode($c?->address ?? '');
                        $routeUrl = "https://www.google.com/maps/dir/?api=1&destination={$dest}";
                        $isEditable = $s->isEditableBy(auth()->user());
                    @endphp
                    <div class="col">
                        <x-task-card title="{{ $c?->name ?? 'Pelanggan' }}" code="{{ $c?->customer_code }}" badge="{{ $s->status->label() }}" badgeColor="{{ $s->status->badgeColor() }}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-muted">Hasil Survei:</span>
                                <x-status-badge type="survey_result" :status="$s->result->value" />
                            </div>

                            @if($s->result_note)
                                <div class="alert alert-light border py-1 px-2 small mb-2 text-break">
                                    <strong>Catatan:</strong> {{ $s->result_note }}
                                </div>
                            @endif

                            @if($s->status === \App\Enums\SurveyStatus::RESURVEY_REQUESTED && $s->review_note)
                                <div class="alert alert-warning py-1 px-2 small mb-2 text-break">
                                    <strong>Catatan Admin:</strong> {{ $s->review_note }}
                                </div>
                            @endif

                            <div class="small mb-1">
                                <span class="text-muted">ODC / ODP:</span>
                                <span class="text-dark fw-medium">{{ $s->odc?->name ?? '-' }} / {{ $s->odp?->name ?? '-' }}</span>
                            </div>

                            @if($hasCoord)
                                <div class="small mb-1 text-muted">
                                    {{ $s->latitude }}, {{ $s->longitude }}
                                </div>
                            @endif

                            <div class="small text-muted mb-2">
                                {{ $s->created_at->format('d/m/Y H:i') }}
                            </div>

                            @if($s->items->isNotEmpty())
                                <div class="border rounded p-2 bg-light small mb-2">
                                    <div class="fw-semibold mb-1 text-dark">Material Diajukan:</div>
                                    <ul class="list-unstyled mb-0 ps-1">
                                        @foreach($s->items as $it)
                                            <li>• {{ $it->material?->name }}: <strong>{{ $it->quantity }} {{ $it->material?->unit }}</strong></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <x-slot:actions>
                                @if($c?->phone)
                                    <x-btn-icon action="call" href="tel:{{ $c->phone }}" title="Telepon Pelanggan" />
                                @endif
                                <x-btn-icon action="route" href="{{ $routeUrl }}" target="_blank" rel="noopener noreferrer" title="Rute Maps" />
                                @if($isEditable)
                                    <x-btn variant="warning" class="btn-edit-survey" data-id="{{ $s->id }}">
                                        Ubah Survei
                                    </x-btn>
                                @endif
                            </x-slot:actions>
                        </x-task-card>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $surveys->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Offcanvas Form Edit Survei --}}
<x-offcanvas-form id="editSurveyOffcanvas" formId="editSurveyForm" title="Perbarui Hasil Survei" subtitle="Ubah hasil survei atau lengkapi catatan peninjauan admin." method="PUT">
    <input type="hidden" name="survey_id" id="edit_survey_id">

    <div class="alert alert-light border mb-3">
        <div class="small text-muted">Calon Pelanggan</div>
        <div class="fw-bold text-dark " id="edit_customer_display">-</div>
        <div class="small text-muted" id="edit_customer_address">-</div>
    </div>

    <div id="edit_review_note_alert" class="alert alert-warning py-2 small mb-3 d-none">
        <strong>Catatan Admin:</strong> <span id="edit_review_note_text"></span>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-12">
            <label class="form-label small fw-semibold">ODC Terdekat</label>
            <select name="odc_id" id="edit_odc_id" class="form-select form-select-sm">
                <option value="">-- Pilih ODC --</option>
                @foreach($odcs as $odc)
                    <option value="{{ $odc->id }}">{{ $odc->name }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback"></div>
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">ODP Terdekat</label>
            <select name="odp_id" id="edit_odp_id" class="form-select form-select-sm">
                <option value="">-- Pilih ODP --</option>
                @foreach($odps as $odp)
                    <option value="{{ $odp->id }}" data-odc="{{ $odp->odc_id }}">{{ $odp->name }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback"></div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Koordinat Lokasi (Latitude, Longitude)</label>
        <div class="input-group input-group-sm">
            <input type="text" name="coordinates" id="edit_coordinates" class="form-control form-control-sm" placeholder="Contoh: 0.5101234, 101.4412345">
            <x-btn variant="neutral" type="button" id="btn-edit-get-location">Ambil Lokasi</x-btn>
        </div>
        <div class="invalid-feedback d-none" id="edit-coordinates-error"></div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Hasil Kelayakan Survei <span class="text-danger">*</span></label>
        <div class="d-flex gap-3 mt-1">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="result" id="edit_result_feasible" value="FEASIBLE">
                <label class="form-check-label fw-medium text-success" for="edit_result_feasible">
                    Layak (Feasible)
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="result" id="edit_result_not_feasible" value="NOT_FEASIBLE">
                <label class="form-check-label fw-medium text-danger" for="edit_result_not_feasible">
                    Tidak Layak (Not Feasible)
                </label>
            </div>
        </div>
    </div>

    <div class="mb-3 d-none" id="edit_result_note_wrapper">
        <label class="form-label small fw-semibold">Catatan Alasan Tidak Layak <span class="text-danger">*</span></label>
        <textarea name="result_note" id="edit_result_note" class="form-control form-control-sm" rows="3" placeholder="Jelaskan alasan teknis mengapa lokasi tidak layak..."></textarea>
        <div class="invalid-feedback"></div>
    </div>

    <div id="edit_materials_section" class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label small fw-semibold mb-0">Kebutuhan Barang &amp; Material Lapangan <span class="text-danger" id="edit_materials_required_star">*</span></label>
            <x-btn variant="primary" type="button" id="btn-edit-add-material">Tambah Barang</x-btn>
        </div>
        <div class="small text-muted mb-2">Wajib diisi minimal 1 item jika Layak. Maksimal 30 jenis barang, tanpa barang ganda.</div>

        <div class="table-responsive border rounded">
            <table class="table table-sm table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Satuan</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="edit_materials_tbody">
                </tbody>
            </table>
        </div>
        <div class="invalid-feedback d-none" id="edit-items-error"></div>
    </div>
</x-offcanvas-form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editOdcSelect = document.getElementById('edit_odc_id');
    const editOdpSelect = document.getElementById('edit_odp_id');
    const editOdpOptions = editOdpSelect ? Array.from(editOdpSelect.querySelectorAll('option[data-odc]')) : [];
    const editResultFeasible = document.getElementById('edit_result_feasible');
    const editResultNotFeasible = document.getElementById('edit_result_not_feasible');
    const editResultNoteWrapper = document.getElementById('edit_result_note_wrapper');
    const editMaterialsTbody = document.getElementById('edit_materials_tbody');
    const btnEditAddMaterial = document.getElementById('btn-edit-add-material');
    const btnEditGetLocation = document.getElementById('btn-edit-get-location');
    const editCoordsInput = document.getElementById('edit_coordinates');
    const editSurveyForm = document.getElementById('editSurveyForm');
    const availableMaterials = @json($materials);

    function filterEditOdp() {
        const selectedOdc = editOdcSelect.value;
        const currentOdpVal = editOdpSelect.value;
        let found = false;

        editOdpOptions.forEach(opt => {
            const match = !selectedOdc || opt.getAttribute('data-odc') === selectedOdc;
            opt.style.display = match ? '' : 'none';
            opt.disabled = !match;
            if (match && opt.value === currentOdpVal) {
                found = true;
            }
        });

        if (!found) {
            editOdpSelect.value = '';
        }
    }

    if (editOdcSelect) {
        editOdcSelect.addEventListener('change', filterEditOdp);
    }

    function toggleEditResultState() {
        if (editResultNotFeasible.checked) {
            editResultNoteWrapper.classList.remove('d-none');
            const star = document.getElementById('edit_materials_required_star');
            if (star) star.classList.add('d-none');
        } else {
            editResultNoteWrapper.classList.add('d-none');
            const star = document.getElementById('edit_materials_required_star');
            if (star) star.classList.remove('d-none');
        }
    }

    editResultFeasible?.addEventListener('change', toggleEditResultState);
    editResultNotFeasible?.addEventListener('change', toggleEditResultState);

    function addEditMaterialRow(materialId = '', quantity = 1) {
        const rowCount = editMaterialsTbody.querySelectorAll('tr').length;
        if (rowCount >= 30) {
            if (typeof window.toast === 'function') window.toast('warning', 'Maksimal 30 jenis barang.');
            return;
        }

        const tr = document.createElement('tr');
        const idx = Date.now() + Math.floor(Math.random() * 100);

        let optionsHtml = '<option value="">-- Pilih Barang --</option>';
        availableMaterials.forEach(m => {
            const selected = String(m.id) === String(materialId) ? 'selected' : '';
            optionsHtml += `<option value="${m.id}" data-unit="${m.unit}" ${selected}>${m.name} (${m.unit})</option>`;
        });

        const delBtnHtml = window.btnIconHtml('delete', { class: 'btn-del-row', title: 'Hapus' });

        tr.innerHTML = `
            <td>
                <select name="items[${idx}][material_id]" class="form-select form-select-sm material-select" required>
                    ${optionsHtml}
                </select>
            </td>
            <td>
                <input type="number" step="0.01" min="0.01" max="99999.99" name="items[${idx}][quantity]" class="form-control form-control-sm" value="${quantity}" required>
            </td>
            <td class="small text-muted unit-cell">-</td>
            <td class="text-center">
                ${delBtnHtml}
            </td>
        `;

        editMaterialsTbody.appendChild(tr);

        const select = tr.querySelector('.material-select');
        const unitCell = tr.querySelector('.unit-cell');
        function updateUnit() {
            const opt = select.selectedOptions[0];
            unitCell.textContent = opt ? (opt.getAttribute('data-unit') || '-') : '-';
        }
        select.addEventListener('change', updateUnit);
        updateUnit();

        tr.querySelector('.btn-del-row').addEventListener('click', function () {
            tr.remove();
        });
    }

    btnEditAddMaterial?.addEventListener('click', function () {
        addEditMaterialRow();
    });

    btnEditGetLocation?.addEventListener('click', function () {
        if (!navigator.geolocation) {
            if (typeof window.toast === 'function') window.toast('error', 'Browser tidak mendukung geolokasi.');
            return;
        }
        btnEditGetLocation.disabled = true;
        btnEditGetLocation.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mencari...';

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const lat = pos.coords.latitude.toFixed(7);
                const lng = pos.coords.longitude.toFixed(7);
                editCoordsInput.value = `${lat}, ${lng}`;
                btnEditGetLocation.disabled = false;
                btnEditGetLocation.textContent = 'Ambil Lokasi';
                if (typeof window.toast === 'function') window.toast('success', 'Koordinat berhasil diperoleh.');
            },
            function (err) {
                btnEditGetLocation.disabled = false;
                btnEditGetLocation.textContent = 'Ambil Lokasi';
                if (typeof window.toast === 'function') window.toast('warning', 'Gagal mengambil koordinat: ' + err.message);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    // Edit Survey Button Click (Event Delegation)
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.btn-edit-survey');
        if (!btn) return;
        const surveyId = btn.getAttribute('data-id');
            try {
                const res = await window.apiRequest(`/my/surveys/${surveyId}`, { method: 'GET' });
                const s = res.survey;

                document.getElementById('edit_survey_id').value = s.id;
                document.getElementById('edit_customer_display').textContent = `${s.customer_code} - ${s.customer_name}`;
                document.getElementById('edit_customer_address').textContent = s.customer_address || '-';

                const alertReview = document.getElementById('edit_review_note_alert');
                if (s.review_note && s.status === 'RESURVEY_REQUESTED') {
                    document.getElementById('edit_review_note_text').textContent = s.review_note;
                    alertReview.classList.remove('d-none');
                } else {
                    alertReview.classList.add('d-none');
                }

                editCoordsInput.value = s.coordinates || '';
                if (editOdcSelect) editOdcSelect.value = s.odc_id || '';
                filterEditOdp();
                if (editOdpSelect) editOdpSelect.value = s.odp_id || '';

                if (s.result === 'FEASIBLE') {
                    editResultFeasible.checked = true;
                } else {
                    editResultNotFeasible.checked = true;
                }
                toggleEditResultState();
                document.getElementById('edit_result_note').value = s.result_note || '';

                // Populate materials
                editMaterialsTbody.innerHTML = '';
                if (s.items && s.items.length > 0) {
                    s.items.forEach(it => {
                        addEditMaterialRow(it.material_id, it.quantity);
                    });
                } else if (availableMaterials.length > 0) {
                    addEditMaterialRow(availableMaterials[0].id, 1);
                }

                if (window.openOffcanvas) {
                    window.openOffcanvas('editSurveyOffcanvas');
                }
            } catch (err) {
                if (typeof window.toast === 'function') {
                    window.toast('error', err.message || 'Gagal memuat detail survei.');
                }
            }
    });

    // Submit Edit Survey
    editSurveyForm?.addEventListener('submit', async function (e) {
        e.preventDefault();

        editSurveyForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        editSurveyForm.querySelectorAll('.invalid-feedback').forEach(el => {
            el.textContent = '';
            el.classList.add('d-none');
        });

        const surveyId = document.getElementById('edit_survey_id').value;
        const formData = new FormData(editSurveyForm);
        const payload = {
            odc_id: formData.get('odc_id') || null,
            odp_id: formData.get('odp_id') || null,
            coordinates: formData.get('coordinates'),
            result: formData.get('result'),
            result_note: formData.get('result_note'),
            items: []
        };

        const rows = editMaterialsTbody.querySelectorAll('tr');
        rows.forEach(tr => {
            const matSel = tr.querySelector('.material-select');
            const qtyInput = tr.querySelector('input[type="number"]');
            if (matSel && matSel.value && qtyInput && qtyInput.value) {
                payload.items.push({
                    material_id: parseInt(matSel.value, 10),
                    quantity: parseFloat(qtyInput.value)
                });
            }
        });

        try {
            const res = await window.apiRequest(`/my/surveys/${surveyId}`, {
                method: 'PUT',
                body: JSON.stringify(payload)
            });

            if (window.closeOffcanvas) {
                window.closeOffcanvas('editSurveyOffcanvas');
            }
            if (typeof window.toast === 'function') {
                window.toast('success', res.message || 'Survei berhasil diperbarui.');
            }
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } catch (err) {
            if (err.status === 422 && err.data && err.data.errors) {
                const errors = err.data.errors;
                Object.keys(errors).forEach(key => {
                    const msg = errors[key][0];
                    if (key === 'result_note') {
                        const el = document.getElementById('edit_result_note');
                        if (el) {
                            el.classList.add('is-invalid');
                            if (el.nextElementSibling) el.nextElementSibling.textContent = msg;
                        }
                    } else if (key === 'coordinates') {
                        editCoordsInput.classList.add('is-invalid');
                        const errEl = document.getElementById('edit-coordinates-error');
                        if (errEl) {
                            errEl.textContent = msg;
                            errEl.classList.remove('d-none');
                        }
                    } else if (key.startsWith('items')) {
                        const errEl = document.getElementById('edit-items-error');
                        if (errEl) {
                            errEl.textContent = msg;
                            errEl.classList.remove('d-none');
                        }
                    } else {
                        const el = editSurveyForm.querySelector(`[name="${key}"]`);
                        if (el) {
                            el.classList.add('is-invalid');
                            if (el.nextElementSibling && el.nextElementSibling.classList.contains('invalid-feedback')) {
                                el.nextElementSibling.textContent = msg;
                            }
                        }
                    }
                });
                if (typeof window.toast === 'function') {
                    window.toast('warning', err.data.message || 'Periksa kembali data formulir.');
                }
            } else {
                if (typeof window.toast === 'function') {
                    window.toast('error', err.message || 'Gagal memperbarui survei.');
                }
            }
        }
    });

    // ── Filter State Interaktif (Fase 17k) ───────────────────────────────────
    if (window.initCardFilterPartial) {
        window.initCardFilterPartial('#filterSurveysForm', '#surveysCardsWrapper', "{{ route('surveyor.surveys.index') }}");
    }
});
</script>
@endpush
@endsection
