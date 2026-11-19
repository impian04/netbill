@extends('layouts.app')

@section('title', 'Pendaftaran Saya')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Pendaftaran Saya">
    <x-slot:subtitle>
        POP: <x-status-badge type="role" :status="$pop?->code" /> {{ $pop?->name }} | Kelola pendaftaran calon pelanggan baru Anda
    </x-slot:subtitle>
    <x-slot:actions>
        <x-btn variant="primary" id="btn-add-application">Pendaftaran Baru</x-btn>
    </x-slot:actions>
</x-page-header>

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<x-filter-card>
    <form id="filter-applications-form" class="row g-2 align-items-center">
        <div class="col-12 col-lg-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"></span>
                <input type="search" id="filter-app-search" name="search" class="form-control form-control-sm"
                       placeholder="Cari nama calon, telepon, atau alamat...">
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <select name="status" id="filter-status" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                <option value="SUBMITTED">Menunggu Verifikasi</option>
                <option value="NEEDS_REVISION">Perlu Perbaikan</option>
                <option value="APPROVED">Disetujui</option>
                <option value="REJECTED">Ditolak</option>
            </select>
        </div>

        <div class="col-6 col-lg-2 ms-lg-auto text-end">
            <x-btn variant="neutral" id="btn-reset-app-filter" class="w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
    <table id="table-marketing-applications" class="table table-hover align-middle mb-0 w-100">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Nama Calon</th>
                <th>No. Telepon</th>
                <th>Paket</th>
                <th>Status</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</x-table-card>

{{-- ── Offcanvas Form (Tambah & Ubah) ─────────────────────────── --}}
<x-offcanvas-form id="offcanvas-application" formId="form-application" title="Pendaftaran Calon Pelanggan" titleId="offcanvas-title">
    <input type="hidden" name="_method" id="form-method" value="POST">
    <input type="hidden" id="application-id" value="">

    <!-- Review note banner for NEEDS_REVISION or REJECTED -->
            <div id="review-note-container" class="alert alert-warning mb-3 d-none">
                <div class="d-flex align-items-start gap-2">
                    <div>
                        <strong class="d-block" id="review-note-title">Catatan Verifikator:</strong>
                        <div id="review-note-text" class="small mt-1"></div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label for="app-name" class="form-label small fw-semibold">
                        Nama Calon Pelanggan <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="name" id="app-name" class="form-control form-control-sm" placeholder="Nama lengkap sesuai KTP" required>
                    <div class="invalid-feedback" id="err-name"></div>
                </div>

                <div class="col-12">
                    <label for="app-phone" class="form-label small fw-semibold">
                        Nomor Telepon / WhatsApp <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="phone" id="app-phone" class="form-control form-control-sm" placeholder="Contoh: 081234567890" required>
                    <div class="invalid-feedback" id="err-phone"></div>
                </div>

                <div class="col-12">
                    <label for="app-address" class="form-label small fw-semibold">
                        Alamat Pemasangan <span class="text-danger">*</span>
                    </label>
                    <textarea name="address" id="app-address" class="form-control form-control-sm" rows="3" placeholder="Alamat lengkap, patokan, RT/RW" required></textarea>
                    <div class="invalid-feedback" id="err-address"></div>
                </div>

                <!-- Koordinat -->
                <div class="col-12">
                    <label for="app-coordinates" class="form-label small fw-semibold">Koordinat Lokasi (Latitude, Longitude)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="coordinates" id="app-coordinates" class="form-control form-control-sm" placeholder="Contoh: 0.5071, 101.4478">
                        <x-btn variant="neutral" type="button" id="btn-get-location" title="Gunakan Lokasi Saya">Lokasi Saya</x-btn>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="text-muted small">Derajat desimal (lat -11.5..6.5, lng 94..141.5)</span>
                        <a href="#" target="_blank" rel="noopener noreferrer" id="link-open-map" class="small text-decoration-none d-none">
                            Buka di Peta
                        </a>
                    </div>
                    <div class="invalid-feedback d-block" id="err-coordinates"></div>
                    <div class="invalid-feedback d-block" id="err-latitude"></div>
                    <div class="invalid-feedback d-block" id="err-longitude"></div>
                </div>

                <div class="col-12">
                    <label for="app-package" class="form-label small fw-semibold">
                        Paket Layanan <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="package" id="app-package" class="form-control form-control-sm" list="package-list" placeholder="Pilih atau ketik nama paket" required>
                    <datalist id="package-list">
                        @foreach($packages as $pkg)
                            <option value="{{ $pkg }}"></option>
                        @endforeach
                    </datalist>
                    <div class="invalid-feedback" id="err-package"></div>
                </div>

                <div class="col-12">
                    <label for="app-billing-type" class="form-label small fw-semibold">Tipe Tagihan</label>
                    <select name="billing_type" id="app-billing-type" class="form-select form-select-sm">
                        <option value="Bulanan" selected>Bulanan</option>
                        <option value="Tahunan">Tahunan</option>
                        <option value="Pascabayar">Pascabayar</option>
                        <option value="Prabayar">Prabayar</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="app-notes" class="form-label small fw-semibold">Catatan Tambahan</label>
                    <textarea name="notes" id="app-notes" class="form-control form-control-sm" rows="2" placeholder="Catatan khusus dari calon pelanggan atau informasi survei"></textarea>
                </div>

                <!-- KTP File Upload & Preview -->
                <div class="col-12">
                    <label for="app-ktp-file" class="form-label small fw-semibold">Foto / Berkas KTP (Opsional)</label>
                    <input type="file" name="ktp_file" id="app-ktp-file" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text small text-muted">Format: JPG, PNG, WEBP (maks. 3 MB). Disimpan di storage privat aman.</div>
                    <div class="invalid-feedback" id="err-ktp-file"></div>

                    <div id="ktp-preview-wrapper" class="mt-2 d-none">
                        <div class="d-flex align-items-center justify-content-between p-2 border rounded bg-light">
                            <div class="d-flex align-items-center gap-2">
                                <a href="#" target="_blank" rel="noopener noreferrer" id="ktp-preview-link" class="small fw-semibold text-decoration-none">
                                    Lihat Berkas KTP Saat Ini
                                </a>
                            </div>
                            <div class="form-check form-check-inline mb-0" id="delete-ktp-group">
                                <input class="form-check-input" type="checkbox" name="delete_ktp" id="delete-ktp" value="1">
                                <label class="form-check-label text-danger small" for="delete-ktp">Hapus KTP</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    <x-slot:footer>
        <div class="d-flex justify-content-end gap-2 w-100" id="action-buttons-container">
            <x-btn variant="neutral" data-bs-dismiss="offcanvas">Tutup</x-btn>
            <x-btn type="submit" variant="primary" id="btn-submit-application">Kirim Pendaftaran</x-btn>
        </div>
    </x-slot:footer>
</x-offcanvas-form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const offcanvasEl = document.getElementById('offcanvas-application');
    const offcanvas = {
        show: () => window.showOffcanvas(offcanvasEl),
        hide: () => window.hideOffcanvas(offcanvasEl),
    };
    const form = document.getElementById('form-application');
    const formMethod = document.getElementById('form-method');
    const appIdInput = document.getElementById('application-id');
    const offcanvasTitle = document.getElementById('offcanvas-title');
    const submitBtn = document.getElementById('btn-submit-application');
    const reviewNoteContainer = document.getElementById('review-note-container');
    const reviewNoteTitle = document.getElementById('review-note-title');
    const reviewNoteText = document.getElementById('review-note-text');
    const ktpPreviewWrapper = document.getElementById('ktp-preview-wrapper');
    const ktpPreviewLink = document.getElementById('ktp-preview-link');
    const deleteKtpCheckbox = document.getElementById('delete-ktp');
    const deleteKtpGroup = document.getElementById('delete-ktp-group');
    const coordInput = document.getElementById('app-coordinates');
    const mapLink = document.getElementById('link-open-map');

    function updateMapLink() {
        const val = coordInput.value.trim();
        if (val && val.includes(',')) {
            mapLink.href = 'https://www.google.com/maps?q=' + encodeURIComponent(val);
            mapLink.classList.remove('d-none');
        } else {
            mapLink.classList.add('d-none');
        }
    }
    coordInput.addEventListener('input', updateMapLink);

    document.getElementById('btn-get-location')?.addEventListener('click', function () {
        if (!navigator.geolocation) {
            window.toast('error', 'Geolocation tidak didukung browser ini.');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            pos => {
                coordInput.value = `${pos.coords.latitude.toFixed(7)}, ${pos.coords.longitude.toFixed(7)}`;
                updateMapLink();
                window.toast('success', 'Lokasi berhasil diambil.');
            },
            err => {
                window.toast('error', 'Gagal mengambil lokasi: ' + err.message);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    const table = window.initServerTable('#table-marketing-applications', {
        ajaxUrl: '{{ route('marketing.applications.index') }}',
        filterForm: '#filter-applications-form',
        searchInput: '#filter-app-search',
        resetButton: '#btn-reset-app-filter',
        pageLength: 15,
        order: [[0, 'desc']],
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'name', name: 'name', className: 'fw-semibold text-dark' },
            { data: 'phone', name: 'phone' },
            { data: 'package', name: 'package' },
            {
                data: 'status',
                name: 'status',
                render: function (data, type, row) {
                    return window.statusBadge('application_status', data || row.status);
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end',
                render: function (data, type, row) {
                    const action = row.can_edit ? 'edit' : 'view';
                    return window.btnIconHtml(action, {
                        'data-id': row.id,
                        'class': 'btn-edit-app',
                        'title': row.can_edit ? 'Ubah' : 'Detail',
                    });
                }
            }
        ]
    });

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => { el.textContent = ''; });
    }

    document.getElementById('btn-add-application')?.addEventListener('click', function () {
        clearErrors();
        form.reset();
        formMethod.value = 'POST';
        appIdInput.value = '';
        offcanvasTitle.textContent = 'Pendaftaran Calon Pelanggan Baru';
        submitBtn.innerHTML = 'Kirim Pendaftaran';
        submitBtn.classList.remove('d-none');
        reviewNoteContainer.classList.add('d-none');
        ktpPreviewWrapper.classList.add('d-none');
        updateMapLink();

        // Enable all inputs
        form.querySelectorAll('input, select, textarea').forEach(el => el.removeAttribute('disabled'));

        offcanvas.show();
    });

    function validateKtpFile() {
        const fileInput = document.getElementById('app-ktp');
        if (fileInput && fileInput.files && fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const maxBytes = 3 * 1024 * 1024;
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (file.size > maxBytes) {
                fileInput.classList.add('is-invalid');
                const errEl = document.getElementById('err-ktp_file');
                if (errEl) {
                    errEl.textContent = 'Ukuran berkas melebihi batas 3 MB.';
                    errEl.style.display = 'block';
                }
                fileInput.focus();
                window.toast('warning', 'Ukuran berkas KTP melebihi batas 3 MB.');
                return false;
            }
            if (!allowedTypes.includes(file.type)) {
                fileInput.classList.add('is-invalid');
                const errEl = document.getElementById('err-ktp_file');
                if (errEl) {
                    errEl.textContent = 'Format berkas harus JPG, PNG, atau WEBP.';
                    errEl.style.display = 'block';
                }
                fileInput.focus();
                window.toast('warning', 'Format berkas KTP tidak valid.');
                return false;
            }
        }
        return true;
    }

    $(document).on('click', '.btn-edit-app', async function () {
        const id = $(this).data('id');
        clearErrors();
        form.reset();
        updateMapLink();

        try {
            const data = await window.apiRequest(`{{ url('my/applications') }}/${id}`);

            appIdInput.value = data.id;
            formMethod.value = 'PUT';
            document.getElementById('app-name').value = data.name || '';
            document.getElementById('app-phone').value = data.phone || '';
            document.getElementById('app-address').value = data.address || '';
            document.getElementById('app-coordinates').value = data.coordinates || '';
            document.getElementById('app-package').value = data.package || '';
            document.getElementById('app-billing-type').value = data.billing_type || 'Bulanan';
            document.getElementById('app-notes').value = data.notes || '';
            updateMapLink();

            if (data.review_note) {
                reviewNoteContainer.classList.remove('d-none');
                reviewNoteTitle.textContent = data.status === 'NEEDS_REVISION' ? 'Catatan Perbaikan dari Admin:' : 'Alasan Penolakan:';
                reviewNoteText.textContent = data.review_note;
            } else {
                reviewNoteContainer.classList.add('d-none');
            }

            if (data.has_ktp && data.ktp_url) {
                ktpPreviewWrapper.classList.remove('d-none');
                ktpPreviewLink.href = data.ktp_url;
                if (data.can_edit) {
                    deleteKtpGroup.classList.remove('d-none');
                } else {
                    deleteKtpGroup.classList.add('d-none');
                }
            } else {
                ktpPreviewWrapper.classList.add('d-none');
            }

            if (data.can_edit) {
                form.querySelectorAll('input, select, textarea').forEach(el => el.removeAttribute('disabled'));
                offcanvasTitle.textContent = data.status === 'NEEDS_REVISION' ? 'Kirim Ulang Perbaikan Pendaftaran' : 'Ubah Data Pendaftaran';
                submitBtn.innerHTML = data.status === 'NEEDS_REVISION' ? 'Kirim Ulang Perbaikan' : 'Simpan Perubahan';
                submitBtn.classList.remove('d-none');
            } else {
                // Read-only
                form.querySelectorAll('input, select, textarea').forEach(el => el.setAttribute('disabled', 'disabled'));
                offcanvasTitle.textContent = 'Detail Pendaftaran (' + data.status_label + ')';
                submitBtn.classList.add('d-none');
            }

            offcanvas.show();
        } catch (err) {
            window.toast('error', err.message || 'Gagal memuat data pendaftaran.');
        }
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearErrors();

        if (!validateKtpFile()) {
            return;
        }

        const isUpdate = formMethod.value === 'PUT';
        const url = isUpdate
            ? `{{ url('my/applications') }}/${appIdInput.value}`
            : "{{ route('marketing.applications.store') }}";

        const formData = new FormData(form);

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';

        try {
            const result = await window.apiRequest(url, {
                method: 'POST',
                body: formData,
            });

            offcanvas.hide();
            window.toast('success', result.message || 'Berhasil disimpan.');
            table.ajax.reload(null, false);
        } catch (err) {
            if (err.status === 413) {
                window.toast('error', 'Ukuran berkas melebihi batas server.');
            } else if (err.status === 422 && err.data?.errors) {
                let firstInvalidEl = null;
                for (const [key, msgs] of Object.entries(err.data.errors)) {
                    const input = form.querySelector(`[name="${key}"]`) || (key === 'ktp_file' ? document.getElementById('app-ktp') : null);
                    const errFeedback = document.getElementById(`err-${key}`) || (key === 'ktp_file' ? document.getElementById('err-ktp_file') : null);
                    if (input) {
                        input.classList.add('is-invalid');
                        if (!firstInvalidEl) firstInvalidEl = input;
                    }
                    if (errFeedback) {
                        errFeedback.textContent = msgs[0];
                        errFeedback.style.display = 'block';
                    }
                }
                if (firstInvalidEl) firstInvalidEl.focus();
                window.toast('warning', 'Mohon periksa kembali isian form Anda.');
            } else {
                window.toast('error', err.message || 'Terjadi kesalahan sistem.');
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = isUpdate ? 'Simpan Perubahan' : 'Kirim Pendaftaran';
        }
    });
});
</script>
@endpush
