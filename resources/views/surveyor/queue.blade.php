@extends('layouts.app')

@section('title', 'Antrean Survei')

@section('content')
<x-page-header title="Antrean Survei" subtitle="Daftar calon pelanggan baru berstatus Pending di POP Anda yang menunggu survei lapangan.">
    <x-btn href="{{ route('surveyor.surveys.index') }}" variant="neutral">Survei Saya</x-btn>
</x-page-header>

<x-filter-card action="{{ route('surveyor.queue.index') }}" id="filterQueueForm" mode="instant">
    <div class="col-12 col-md-6 col-lg-4">
        <label class="form-label small fw-semibold">Pencarian</label>
        <div class="input-group input-group-sm">
            <input type="search" name="q" id="queue_filter_q" class="form-control form-control-sm" placeholder="Nama, kode, telepon, alamat..." value="{{ $search }}">
            <x-btn type="submit" variant="primary">Cari</x-btn>
            <x-btn type="reset" variant="neutral" id="btn-reset-queue">Reset</x-btn>
        </div>
    </div>
</x-filter-card>

<div class="row" id="queueCardsWrapper">
    <div class="col-12">
        @if($customers->isEmpty())
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <h5 class="fw-semibold text-dark">Tidak Ada Antrean Survei</h5>
                    <p class="text-muted mb-0">Semua calon pelanggan berstatus pending di POP Anda telah disurvei.</p>
                </div>
            </div>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @foreach($customers as $c)
                    @php
                        $hasCoord = $c->latitude && $c->longitude;
                        $dest = $hasCoord ? "{$c->latitude},{$c->longitude}" : urlencode($c->address);
                        $routeUrl = "https://www.google.com/maps/dir/?api=1&destination={$dest}";
                    @endphp
                    <div class="col">
                        <x-task-card title="{{ $c->name }}" code="{{ $c->customer_code }}" badge="Pending" badgeColor="warning">
                            <div class="mb-2">
                                <div class="text-muted small">Alamat:</div>
                                <div class="text-dark fw-medium text-break">{{ $c->address }}</div>
                            </div>
                            <div class="mb-2">
                                <div class="text-muted small">Telepon:</div>
                                <div class="text-dark fw-medium">{{ $c->phone }}</div>
                            </div>
                            @if($c->odc || $c->odp)
                                <div class="mb-2 small text-muted">
                                    Perkiraan ODC/ODP: <span class="text-dark">{{ $c->odc?->name ?? '-' }} / {{ $c->odp?->name ?? '-' }}</span>
                                </div>
                            @endif
                            @if($hasCoord)
                                <div class="small text-muted mb-2">
                                    {{ $c->latitude }}, {{ $c->longitude }}
                                </div>
                            @endif

                            <x-slot:actions>
                                <x-btn-icon action="call" href="tel:{{ $c->phone }}" title="Telepon Pelanggan" />
                                <x-btn-icon action="route" href="{{ $routeUrl }}" target="_blank" rel="noopener noreferrer" title="Rute Maps" />
                                <x-btn variant="approve" class="btn-start-survey"
                                        data-id="{{ $c->id }}"
                                        data-code="{{ $c->customer_code }}"
                                        data-name="{{ $c->name }}"
                                        data-odc-id="{{ $c->odc_id }}"
                                        data-odp-id="{{ $c->odp_id }}"
                                        data-coords="{{ $hasCoord ? ($c->latitude . ', ' . $c->longitude) : '' }}">
                                    Mulai Survei
                                </x-btn>
                            </x-slot:actions>
                        </x-task-card>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Offcanvas Form Survei Baru --}}
<x-offcanvas-form id="surveyOffcanvas" formId="surveyForm" title="Form Hasil Survei" subtitle="Masukkan hasil pemeriksaan teknis di lokasi pelanggan.">
    <input type="hidden" name="customer_id" id="survey_customer_id">

    <div class="alert alert-light border mb-3">
        <div class="small text-muted">Calon Pelanggan</div>
        <div class="fw-bold text-dark " id="survey_customer_display">-</div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-12">
            <label class="form-label small fw-semibold">ODC Terdekat</label>
            <select name="odc_id" id="survey_odc_id" class="form-select form-select-sm">
                <option value="">-- Pilih ODC --</option>
                @foreach($odcs as $odc)
                    <option value="{{ $odc->id }}">{{ $odc->name }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback"></div>
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">ODP Terdekat</label>
            <select name="odp_id" id="survey_odp_id" class="form-select form-select-sm">
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
            <input type="text" name="coordinates" id="survey_coordinates" class="form-control form-control-sm" placeholder="Contoh: 0.5101234, 101.4412345">
            <x-btn variant="neutral" type="button" id="btn-get-location">Ambil Lokasi</x-btn>
        </div>
        <div class="form-text small">Masukkan format "lat, lng" atau gunakan tombol Ambil Lokasi saat berada di titik instalasi.</div>
        <div class="invalid-feedback d-none" id="coordinates-error"></div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Hasil Kelayakan Survei <span class="text-danger">*</span></label>
        <div class="d-flex gap-3 mt-1">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="result" id="result_feasible" value="FEASIBLE" checked>
                <label class="form-check-label fw-medium text-success" for="result_feasible">
                    Layak (Feasible)
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="result" id="result_not_feasible" value="NOT_FEASIBLE">
                <label class="form-check-label fw-medium text-danger" for="result_not_feasible">
                    Tidak Layak (Not Feasible)
                </label>
            </div>
        </div>
        <div class="invalid-feedback" id="result-error"></div>
    </div>

    <div class="mb-3 d-none" id="result_note_wrapper">
        <label class="form-label small fw-semibold">Catatan Alasan Tidak Layak <span class="text-danger">*</span></label>
        <textarea name="result_note" id="survey_result_note" class="form-control form-control-sm" rows="3" placeholder="Jelaskan alasan teknis mengapa lokasi tidak layak (mis. jarak ODP > 500m, tidak ada tiang, redaman tinggi)..."></textarea>
        <div class="invalid-feedback"></div>
    </div>

    <div id="materials_section" class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label small fw-semibold mb-0">Kebutuhan Barang &amp; Material Lapangan <span class="text-danger" id="materials_required_star">*</span></label>
            <x-btn variant="primary" type="button" id="btn-add-material">Tambah Barang</x-btn>
        </div>
        <div class="small text-muted mb-2">Wajib diisi minimal 1 item jika Layak. Maksimal 30 jenis barang, tanpa barang ganda.</div>

        <div class="table-responsive border rounded">
            <table class="table table-sm table-striped align-middle mb-0" id="survey_materials_table">
                <thead class="table-light">
                    <tr>
                        <th>Nama Barang</th>
                        <th class="text-end">Jumlah</th>
                        <th>Satuan</th>
                        <th class="text-center"></th>
                    </tr>
                </thead>
                <tbody id="materials_tbody">
                    {{-- Diisi secara dinamis oleh JS --}}
                </tbody>
            </table>
        </div>
        <div class="invalid-feedback d-none" id="items-error"></div>
    </div>
</x-offcanvas-form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const odcSelect = document.getElementById('survey_odc_id');
    const odpSelect = document.getElementById('survey_odp_id');
    const odpOptions = odpSelect ? Array.from(odpSelect.querySelectorAll('option[data-odc]')) : [];
    const resultFeasible = document.getElementById('result_feasible');
    const resultNotFeasible = document.getElementById('result_not_feasible');
    const resultNoteWrapper = document.getElementById('result_note_wrapper');
    const materialsSection = document.getElementById('materials_section');
    const materialsRequiredStar = document.getElementById('materials_required_star');
    const materialsTbody = document.getElementById('materials_tbody');
    const btnAddMaterial = document.getElementById('btn-add-material');
    const btnGetLocation = document.getElementById('btn-get-location');
    const coordsInput = document.getElementById('survey_coordinates');
    const surveyForm = document.getElementById('surveyForm');

    const availableMaterials = @json($materials);

    function filterOdp() {
        const selectedOdc = odcSelect.value;
        const currentOdpVal = odpSelect.value;
        let found = false;

        odpOptions.forEach(opt => {
            const match = !selectedOdc || opt.getAttribute('data-odc') === selectedOdc;
            opt.style.display = match ? '' : 'none';
            opt.disabled = !match;
            if (match && opt.value === currentOdpVal) {
                found = true;
            }
        });

        if (!found) {
            odpSelect.value = '';
        }
    }

    if (odcSelect) {
        odcSelect.addEventListener('change', filterOdp);
    }

    function toggleResultState() {
        if (resultNotFeasible.checked) {
            resultNoteWrapper.classList.remove('d-none');
            materialsRequiredStar.classList.add('d-none');
        } else {
            resultNoteWrapper.classList.add('d-none');
            materialsRequiredStar.classList.remove('d-none');
        }
    }

    resultFeasible?.addEventListener('change', toggleResultState);
    resultNotFeasible?.addEventListener('change', toggleResultState);

    // Dynamic Materials Row
    function addMaterialRow(materialId = '', quantity = 1) {
        const rowCount = materialsTbody.querySelectorAll('tr').length;
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

        materialsTbody.appendChild(tr);

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

    btnAddMaterial?.addEventListener('click', function () {
        addMaterialRow();
    });

    // Geolocation
    btnGetLocation?.addEventListener('click', function () {
        if (!navigator.geolocation) {
            if (typeof window.toast === 'function') window.toast('error', 'Browser tidak mendukung geolokasi.');
            return;
        }
        btnGetLocation.disabled = true;
        btnGetLocation.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mencari...';

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const lat = pos.coords.latitude.toFixed(7);
                const lng = pos.coords.longitude.toFixed(7);
                coordsInput.value = `${lat}, ${lng}`;
                btnGetLocation.disabled = false;
                btnGetLocation.textContent = 'Ambil Lokasi';
                if (typeof window.toast === 'function') window.toast('success', 'Koordinat berhasil diperoleh.');
            },
            function (err) {
                btnGetLocation.disabled = false;
                btnGetLocation.textContent = 'Ambil Lokasi';
                if (typeof window.toast === 'function') window.toast('warning', 'Gagal mengambil koordinat: ' + err.message);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    // Start Survey Button (Event Delegation)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-start-survey');
        if (!btn) return;

        const customerId = btn.getAttribute('data-id');
        const code = btn.getAttribute('data-code');
        const name = btn.getAttribute('data-name');
        const odcId = btn.getAttribute('data-odc-id') || '';
        const odpId = btn.getAttribute('data-odp-id') || '';
        const coords = btn.getAttribute('data-coords') || '';

        document.getElementById('survey_customer_id').value = customerId;
        document.getElementById('survey_customer_display').textContent = `${code} - ${name}`;
        coordsInput.value = coords;

        if (odcSelect) odcSelect.value = odcId;
        filterOdp();
        if (odpSelect) odpSelect.value = odpId;

        resultFeasible.checked = true;
        toggleResultState();
        document.getElementById('survey_result_note').value = '';

        // Reset materials table & tambahkan 1 baris default jika tersedia
        materialsTbody.innerHTML = '';
        if (availableMaterials.length > 0) {
            addMaterialRow(availableMaterials[0].id, 1);
        }

        if (window.openOffcanvas) {
            window.openOffcanvas('surveyOffcanvas');
        }
    });

    // ── Filter State Interaktif (Fase 17k) ───────────────────────────────────
    if (window.initCardFilterPartial) {
        window.initCardFilterPartial('#filterQueueForm', '#queueCardsWrapper', "{{ route('surveyor.queue.index') }}");
    }

    // Submit Survey Form via apiRequest
    surveyForm?.addEventListener('submit', async function (e) {
        e.preventDefault();

        // Clear invalid classes
        surveyForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        surveyForm.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');

        const formData = new FormData(surveyForm);
        const custIdVal = document.getElementById('survey_customer_id')?.value || formData.get('customer_id');
        const resultVal = surveyForm.querySelector('input[name="result"]:checked')?.value || formData.get('result');
        const odcVal = formData.get('odc_id') || document.getElementById('survey_odc_id')?.value;
        const odpVal = formData.get('odp_id') || document.getElementById('survey_odp_id')?.value;

        const payload = {
            customer_id: custIdVal ? parseInt(custIdVal, 10) : null,
            odc_id: odcVal ? parseInt(odcVal, 10) : null,
            odp_id: odpVal ? parseInt(odpVal, 10) : null,
            coordinates: formData.get('coordinates') || document.getElementById('survey_coordinates')?.value,
            result: resultVal,
            result_note: formData.get('result_note') || document.getElementById('survey_result_note')?.value,
            items: []
        };

        const rows = materialsTbody.querySelectorAll('tr');
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
            const res = await window.apiRequest('{{ route('surveyor.surveys.store') }}', {
                method: 'POST',
                body: payload
            });

            if (window.closeOffcanvas) {
                window.closeOffcanvas('surveyOffcanvas');
            }
            if (typeof window.toast === 'function') {
                window.toast('success', res.message || 'Survei berhasil dikirim.');
            }
            setTimeout(() => {
                window.location.href = '{{ route('surveyor.surveys.index') }}';
            }, 800);
        } catch (err) {
            if (err.status === 422 && err.data && err.data.errors) {
                const errors = err.data.errors;
                Object.keys(errors).forEach(key => {
                    const msg = errors[key][0];
                    if (key === 'result_note') {
                        const el = document.getElementById('survey_result_note');
                        if (el) {
                            el.classList.add('is-invalid');
                            if (el.nextElementSibling) el.nextElementSibling.textContent = msg;
                        }
                    } else if (key === 'coordinates') {
                        coordsInput.classList.add('is-invalid');
                        const errEl = document.getElementById('coordinates-error');
                        if (errEl) {
                            errEl.textContent = msg;
                            errEl.style.removeProperty('display');
                        }
                    } else if (key.startsWith('items')) {
                        const errEl = document.getElementById('items-error');
                        if (errEl) {
                            errEl.textContent = msg;
                            errEl.style.removeProperty('display');
                        }
                    } else {
                        const el = surveyForm.querySelector(`[name="${key}"]`);
                        if (el) {
                            el.classList.add('is-invalid');
                            if (el.nextElementSibling && el.nextElementSibling.classList.contains('invalid-feedback')) {
                                el.nextElementSibling.textContent = msg;
                            }
                        }
                    }
                });
                if (typeof window.toast === 'function') {
                    window.toast('warning', err.data.message || 'Periksa kembali data formulir survei.');
                }
            } else {
                if (typeof window.toast === 'function') {
                    window.toast('error', err.message || 'Gagal menyimpan hasil survei.');
                }
            }
        }
    });
});
</script>
@endpush
@endsection
