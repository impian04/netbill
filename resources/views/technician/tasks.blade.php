@extends('layouts.app')

@section('title', 'Tugas Saya')

@section('content')
<x-page-header title="Tugas Lapangan" subtitle="Daftar tugas instalasi dan aktivasi pelanggan yang ditugaskan kepada Anda.">
</x-page-header>

<x-filter-card action="{{ route('technician.tasks.index') }}" id="filterTasksForm" mode="instant">
    <div class="col-12 col-md-4 col-lg-3">
        <label class="form-label small fw-semibold">Status Tugas</label>
        <select name="status" id="task_filter_status" class="form-select form-select-sm">
            <option value="">-- Semua Status --</option>
            @foreach(\App\Enums\WorkOrderStatus::cases() as $st)
                <option value="{{ $st->value }}" {{ $status === $st->value ? 'selected' : '' }}>
                    {{ $st->label() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-5 col-lg-4">
        <label class="form-label small fw-semibold">Pencarian</label>
        <div class="input-group input-group-sm">
            <input type="search" name="q" id="task_filter_q" class="form-control form-control-sm" placeholder="Nama, kode, telepon, alamat..." value="{{ $search }}">
            <x-btn type="submit" variant="primary">Cari</x-btn>
            <x-btn type="reset" variant="neutral" id="btn-reset-tasks">Reset</x-btn>
        </div>
    </div>
</x-filter-card>

<div class="row" id="tasksCardsWrapper">
    <div class="col-12">
        @if($tasks->isEmpty())
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <h5 class="fw-semibold text-dark">Belum Ada Tugas</h5>
                    <p class="text-muted mb-0">Tidak ada tugas instalasi atau aktivasi yang ditemukan.</p>
                </div>
            </div>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @foreach($tasks as $t)
                    @php
                        $c = $t->customer;
                        $hasCoord = $c && $c->latitude && $c->longitude;
                        $dest = $hasCoord ? "{$c->latitude},{$c->longitude}" : urlencode($c?->address ?? '');
                        $routeUrl = "https://www.google.com/maps/dir/?api=1&destination={$dest}";
                        $isEditable = $t->isEditableBy(auth()->user());
                    @endphp
                    <div class="col">
                        <x-task-card title="{{ $c?->name ?? 'Pelanggan' }}" code="{{ $c?->customer_code }}" badge="{{ $t->status->label() }}" badgeColor="{{ $t->status->badgeColor() }}">
                            <div class="mb-2">
                                <x-status-badge type="work_order_type" :status="$t->type->value" />
                            </div>

                            @if($t->isRepair() && $t->ticket)
                                <div class="alert alert-warning py-1 px-2 small mb-2 text-break">
                                    <div class="fw-bold">{{ $t->ticket->ticket_number }}</div>
                                    <div class="fw-semibold text-dark">{{ $t->ticket->subject }}</div>
                                    <div class="text-muted fs-xs">{{ $t->ticket->description }}</div>
                                </div>
                            @endif

                            <div class="mb-2">
                                <div class="text-muted small">Alamat:</div>
                                <div class="text-dark fw-medium text-break">{{ $c?->address ?? '-' }}</div>
                            </div>

                            <div class="small mb-2">
                                <span class="text-muted">ODC / ODP:</span>
                                <strong class="text-dark">{{ $c?->odc?->name ?? '-' }} / {{ $c?->odp?->name ?? '-' }}</strong>
                            </div>

                            @if($hasCoord)
                                <div class="small text-muted mb-2">
                                    {{ $c->latitude }}, {{ $c->longitude }}
                                </div>
                            @endif

                            @if($t->scheduled_date)
                                <div class="small text-muted mb-2">
                                    Rencana: <strong class="text-dark">{{ $t->scheduled_date->format('d/m/Y') }}</strong>
                                </div>
                            @endif

                            @if($t->admin_note)
                                <div class="alert alert-light border py-1 px-2 small mb-2 text-break">
                                    <strong>Catatan Admin:</strong> {{ $t->admin_note }}
                                </div>
                            @endif

                            @if($t->status === \App\Enums\WorkOrderStatus::NEEDS_REVISION && $t->review_note)
                                <div class="alert alert-danger py-1 px-2 small mb-2 text-break">
                                    <strong>Perlu Perbaikan:</strong> {{ $t->review_note }}
                                </div>
                            @endif

                            @if($t->survey && $t->survey->items->isNotEmpty())
                                <div class="border rounded p-2 bg-light small mb-2">
                                    <div class="fw-semibold mb-1 text-dark">Material Hasil Survei:</div>
                                    <ul class="list-unstyled mb-0 ps-1">
                                        @foreach($t->survey->items as $it)
                                            <li>• {{ $it->material?->name }}: <strong>{{ $it->quantity }} {{ $it->material?->unit }}</strong></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="small text-muted mb-2">
                                Dokumentasi: <strong>{{ $t->photos->count() }} foto</strong>
                            </div>

                            <x-slot:actions>
                                @if($c?->phone)
                                    <x-btn-icon action="call" href="tel:{{ $c->phone }}" title="Telepon Pelanggan" />
                                @endif
                                <x-btn-icon action="route" href="{{ $routeUrl }}" target="_blank" rel="noopener noreferrer" title="Rute Maps" />

                                @if($t->status === \App\Enums\WorkOrderStatus::ASSIGNED)
                                    <x-btn variant="approve" class="btn-start-task" data-id="{{ $t->id }}">
                                        Mulai
                                    </x-btn>
                                @endif

                                <x-btn variant="{{ $isEditable ? 'primary' : 'neutral' }}" class="btn-open-task" data-id="{{ $t->id }}">
                                    {{ $isEditable ? 'Kerjakan / Foto' : 'Lihat Detail' }}
                                </x-btn>
                            </x-slot:actions>
                        </x-task-card>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $tasks->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Offcanvas Lembar Kerja Teknisi --}}
<x-offcanvas-form
    id="taskWorkOffcanvas"
    title="Lembar Kerja Pemasangan"
    titleId="taskWorkTitle"
    subtitle="-"
    subtitleId="taskWorkSubtitle">
        <input type="hidden" id="work_order_id">

        {{-- Ringkasan Pelanggan --}}
        <div class="card border-0 bg-light mb-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong class="text-dark" id="tk_customer_name">-</strong>
                    <span id="tk_status_badge">-</span>
                </div>
                <div class="small text-muted mb-1"><strong>Alamat:</strong> <span id="tk_customer_address" class="text-dark">-</span></div>
                <div class="small text-muted mb-1"><strong>Telepon:</strong> <span id="tk_customer_phone" class="text-dark">-</span></div>
                <div class="small text-muted mb-1"><strong>ODC / ODP:</strong> <span id="tk_odc_odp" class="text-dark">-</span></div>
                <div class="small text-muted"><strong>Koordinat:</strong> <span id="tk_coordinates" class="text-dark">-</span></div>
            </div>
        </div>

        {{-- Material Survei --}}
        <div class="border rounded p-3 mb-3">
            <h6 class="fw-bold mb-2 text-dark border-bottom pb-2">
                Kebutuhan Barang (Hasil Survei)
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
                    <tbody id="tk_materials_tbody">
                        <tr><td colspan="3" class="text-center text-muted py-2">-</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Form Unggah Foto Dokumentasi (Hanya jika editable) --}}
        <div class="border rounded p-3 mb-3" id="photo_upload_section">
            <h6 class="fw-bold mb-2 text-dark border-bottom pb-2">
                Unggah Foto Dokumentasi
            </h6>
            <div class="small text-muted mb-2">
                Foto akan diperkecil otomatis di browser sebelum dikirim. Wajib minimal <strong>2 foto</strong> (maks 12 foto).
            </div>

            <form id="uploadPhotoForm" novalidate>
                <div class="row g-2 mb-2">
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Pilih Berkas Foto</label>
                        <input type="file" name="photo" id="photo_file_input" class="form-control form-control-sm" accept="image/*" capture="environment" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Tahapan</label>
                        <select name="stage" id="photo_stage_select" class="form-select form-select-sm" required>
                            <option value="INSTALASI">Instalasi Fisik</option>
                            <option value="AKTIVASI">Aktivasi</option>
                            <option value="PERBAIKAN">Perbaikan Fisik / Jaringan</option>
                            <option value="LAINNYA">Lainnya</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Keterangan (Opsional)</label>
                        <input type="text" name="caption" id="photo_caption_input" class="form-control form-control-sm" placeholder="Misal: ODP, ONT, Redaman...">
                    </div>
                    <div class="col-12 d-flex justify-content-end mt-2">
                        <x-btn type="submit" variant="primary" id="btn-upload-photo">
                            Unggah Foto
                        </x-btn>
                    </div>
                </div>
            </form>

            <div id="upload_progress_wrapper" class="progress progress-xs mt-2 d-none">
                <div class="progress-bar progress-bar-striped progress-bar-animated w-100" role="progressbar"></div>
            </div>
        </div>

        {{-- Galeri Foto Dokumentasi --}}
        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                <h6 class="fw-bold mb-0 text-dark">
                    Foto Dokumentasi Terunggah
                </h6>
                <span class="badge bg-light text-dark border" id="tk_photo_counter">0 / 12 Foto</span>
            </div>

            <div class="row row-cols-2 g-2" id="tk_photos_gallery">
                <div class="col-12 text-center text-muted py-3">Belum ada foto terunggah.</div>
            </div>
        </div>

        {{-- Form Penyelesaian & Pengiriman (Hanya jika editable) --}}
        <div class="border rounded p-3 mb-3" id="submit_report_section">
            <h6 class="fw-bold mb-2 text-dark border-bottom pb-2">
                Pengiriman Laporan Selesai
            </h6>
            <form id="submitTaskForm" novalidate>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tanggal Selesai Pemasangan</label>
                    <input type="date" name="completed_on" id="tk_completed_on" class="form-control form-control-sm" required>
                    <div class="invalid-feedback"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Catatan Pengerjaan Teknisi</label>
                    <textarea name="technician_notes" id="tk_technician_notes" class="form-control form-control-sm" rows="3" placeholder="Tuliskan catatan teknis hasil penarikan kabel, redaman dBm, kendala lapangan, dll..."></textarea>
                    <div class="invalid-feedback"></div>
                </div>
                <x-btn type="submit" variant="approve" class="w-100" id="btn-submit-task">
                    Kirim untuk Verifikasi Admin
                </x-btn>
            </form>
        </div>

    <x-slot:footer>
        <x-btn variant="neutral" data-bs-dismiss="offcanvas">Tutup</x-btn>
    </x-slot:footer>
</x-offcanvas-form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let currentWoId = null;
    let isTaskEditable = false;

    // Helper client-side image compression via Canvas
    async function compressImage(file, maxDimension = 1600, quality = 0.8) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function (event) {
                const img = new Image();
                img.src = event.target.result;
                img.onload = function () {
                    let width = img.width;
                    let height = img.height;

                    if (width > maxDimension || height > maxDimension) {
                        if (width >= height) {
                            height = Math.round((height / width) * maxDimension);
                            width = maxDimension;
                        } else {
                            width = Math.round((width / height) * maxDimension);
                            height = maxDimension;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(blob => {
                        if (blob) {
                            resolve(blob);
                        } else {
                            resolve(file);
                        }
                    }, 'image/jpeg', quality);
                };
                img.onerror = () => resolve(file);
            };
            reader.onerror = () => resolve(file);
        });
    }

    // Tombol Mulai Pekerjaan & Buka Lembar Kerja (Event Delegation)
    document.addEventListener('click', async function (e) {
        const startBtn = e.target.closest('.btn-start-task');
        if (startBtn) {
            const id = startBtn.getAttribute('data-id');
            try {
                const res = await window.apiRequest(`{{ url('my/tasks') }}/${id}/start`, { method: 'POST' });
                if (typeof window.toast === 'function') {
                    window.toast('success', res.message || 'Pekerjaan dimulai.');
                }
                const card = startBtn.closest('.card') || startBtn.closest('.col');
                startBtn.remove();
                if (card) {
                    const badge = card.querySelector('.badge');
                    if (badge) {
                        badge.className = 'badge bg-primary';
                        badge.textContent = 'Dikerjakan';
                    }
                }
            } catch (err) {
                if (typeof window.toast === 'function') {
                    window.toast('error', err.message || 'Gagal memulai pekerjaan.');
                }
            }
            return;
        }

        const openBtn = e.target.closest('.btn-open-task');
        if (openBtn) {
            const id = openBtn.getAttribute('data-id');
            currentWoId = id;
            document.getElementById('work_order_id').value = id;

            try {
                const res = await window.apiRequest(`{{ url('my/tasks') }}/${id}`, { method: 'GET' });
                const wo = res.work_order;
                isTaskEditable = wo.is_editable;

                document.getElementById('taskWorkSubtitle').textContent = `${wo.customer_code} - ${wo.customer_name}`;
                document.getElementById('tk_customer_name').textContent = wo.customer_name;
                document.getElementById('tk_customer_address').textContent = wo.customer_address || '-';
                document.getElementById('tk_customer_phone').textContent = wo.customer_phone || '-';
                document.getElementById('tk_odc_odp').textContent = `${wo.odc_name} / ${wo.odp_name}`;
                document.getElementById('tk_coordinates').textContent = wo.coordinates || '-';

                const badgeContainer = document.getElementById('tk_status_badge');
                badgeContainer.innerHTML = window.statusBadge('work_order_status', wo.status || wo.status_label);

                // Material Survei
                const tbody = document.getElementById('tk_materials_tbody');
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

                // Render Foto
                renderPhotos(wo.photos);

                // Section visibility
                const uploadSec = document.getElementById('photo_upload_section');
                const submitSec = document.getElementById('submit_report_section');
                if (wo.is_editable) {
                    uploadSec.style.display = 'block';
                    submitSec.style.display = 'block';
                    document.getElementById('tk_completed_on').value = wo.completed_on || '';
                    document.getElementById('tk_technician_notes').value = wo.technician_notes || '';
                } else {
                    uploadSec.style.display = 'none';
                    submitSec.style.display = 'none';
                }

                if (window.openOffcanvas) {
                    window.openOffcanvas('taskWorkOffcanvas');
                }
            } catch (err) {
                if (typeof window.toast === 'function') {
                    window.toast('error', err.message || 'Gagal memuat tugas.');
                }
            }
        }
    });

    function renderPhotos(photos) {
        const gallery = document.getElementById('tk_photos_gallery');
        const counter = document.getElementById('tk_photo_counter');
        counter.textContent = `${photos.length} / 12 Foto`;

        gallery.innerHTML = '';
        if (photos.length === 0) {
            gallery.innerHTML = '<div class="col-12 text-center text-muted py-3">Belum ada foto terunggah.</div>';
            return;
        }

        photos.forEach(p => {
            const col = document.createElement('div');
            col.className = 'col';

            const delBtn = isTaskEditable ? window.btnIconHtml('delete', {
                class: 'position-absolute top-0 end-0 m-1 btn-del-photo',
                'data-photo-id': p.id,
                title: 'Hapus Foto'
            }) : '';

            const stageBadge = window.statusBadge('photo_stage', p.stage) || `<span class="badge bg-secondary mb-1">${window.escapeHtml(p.stage_label)}</span>`;

            col.innerHTML = `
                <div class="card h-100 border shadow-sm position-relative">
                    <a href="${p.url}" target="_blank" rel="noopener noreferrer">
                        <img src="${p.url}" class="card-img-top img-thumb-120" alt="Foto"
                             onerror="window.handleImageError(this)">
                    </a>
                    ${delBtn}
                    <div class="p-2 small">
                        ${stageBadge}
                        <div class="text-truncate text-muted" title="${window.escapeHtml(p.caption)}">${window.escapeHtml(p.caption)}</div>
                    </div>
                </div>
            `;
            gallery.appendChild(col);
        });

        // Event listener hapus foto
        gallery.querySelectorAll('.btn-del-photo').forEach(btn => {
            btn.addEventListener('click', async function () {
                const photoId = this.getAttribute('data-photo-id');
                let confirmed = true;
                if (typeof window.confirmDialog === 'function') {
                    confirmed = await window.confirmDialog({
                        title: 'Hapus Foto Dokumentasi?',
                        text: 'Foto yang dihapus tidak dapat dipulihkan.',
                        confirmButtonText: 'Ya, Hapus'
                    });
                }
                if (!confirmed) return;

                try {
                    await window.apiRequest(`{{ url('my/tasks') }}/${currentWoId}/photos/${photoId}`, {
                        method: 'DELETE'
                    });
                    if (typeof window.toast === 'function') {
                        window.toast('success', 'Foto berhasil dihapus.');
                    }
                    // Refresh task details
                    const res = await window.apiRequest(`{{ url('my/tasks') }}/${currentWoId}`, { method: 'GET' });
                    renderPhotos(res.work_order.photos);
                } catch (err) {
                    if (typeof window.toast === 'function') {
                        window.toast('error', err.message || 'Gagal menghapus foto.');
                    }
                }
            });
        });
    }

    // Unggah Foto Dokumentasi
    const uploadPhotoForm = document.getElementById('uploadPhotoForm');
    uploadPhotoForm?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const fileInput = document.getElementById('photo_file_input');
        if (!fileInput.files || fileInput.files.length === 0) {
            if (typeof window.toast === 'function') window.toast('warning', 'Pilih berkas foto terlebih dahulu.');
            return;
        }

        const rawFile = fileInput.files[0];
        const btnUpload = document.getElementById('btn-upload-photo');
        const progressWrapper = document.getElementById('upload_progress_wrapper');

        btnUpload.disabled = true;
        btnUpload.textContent = 'Mengompres...';
        progressWrapper.classList.remove('d-none');

        try {
            const compressedBlob = await compressImage(rawFile, 1600, 0.8);

            btnUpload.textContent = 'Mengunggah...';

            const formData = new FormData();
            formData.append('photo', compressedBlob, rawFile.name || 'photo.jpg');
            formData.append('stage', document.getElementById('photo_stage_select').value);
            formData.append('caption', document.getElementById('photo_caption_input').value);

            await window.apiRequest(`{{ url('my/tasks') }}/${currentWoId}/photos`, {
                method: 'POST',
                body: formData
            });

            if (typeof window.toast === 'function') {
                window.toast('success', 'Foto berhasil diunggah.');
            }

            fileInput.value = '';
            document.getElementById('photo_caption_input').value = '';

            const res = await window.apiRequest(`{{ url('my/tasks') }}/${currentWoId}`, { method: 'GET' });
            renderPhotos(res.work_order.photos);
        } catch (err) {
            if (typeof window.toast === 'function') {
                window.toast('error', err.message || 'Gagal mengunggah foto. Periksa koneksi dan coba lagi.');
            }
        } finally {
            btnUpload.disabled = false;
            btnUpload.textContent = 'Unggah Foto';
            progressWrapper.classList.add('d-none');
        }
    });

    // Kirim Laporan Tugas Selesai
    const submitTaskForm = document.getElementById('submitTaskForm');
    submitTaskForm?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const btnSubmit = document.getElementById('btn-submit-task');
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Mengirim...';

        try {
            const res = await window.apiRequest(`{{ url('my/tasks') }}/${currentWoId}/submit`, {
                method: 'POST',
                body: JSON.stringify({
                    completed_on: document.getElementById('tk_completed_on').value,
                    technician_notes: document.getElementById('tk_technician_notes').value
                })
            });

            if (window.closeOffcanvas) {
                window.closeOffcanvas('taskWorkOffcanvas');
            }
            if (typeof window.toast === 'function') {
                window.toast('success', res.message || 'Laporan berhasil dikirim.');
            }
            setTimeout(() => window.location.reload(), 800);
        } catch (err) {
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Kirim untuk Verifikasi Admin';
            if (typeof window.toast === 'function') {
                window.toast('error', err.message || 'Gagal mengirim laporan.');
            }
        }
    });

    // ── Filter State Interaktif (Fase 17k) ───────────────────────────────────
    if (window.initCardFilterPartial) {
        window.initCardFilterPartial('#filterTasksForm', '#tasksCardsWrapper', "{{ route('technician.tasks.index') }}");
    }
});
</script>
@endpush
@endsection
