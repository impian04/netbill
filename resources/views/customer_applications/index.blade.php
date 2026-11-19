@extends('layouts.app')

@section('title', 'Verifikasi Pendaftaran Baru')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Verifikasi Pendaftaran Baru" subtitle="Tinjau dan verifikasi pendaftaran calon pelanggan yang diajukan oleh tim marketing" />

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<x-filter-card>
    <form id="filter-customer-applications-form" class="row g-2 align-items-center">
        <div class="col-12 col-lg-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"></span>
                <input type="search" id="filter-admin-app-search" name="search" class="form-control form-control-sm"
                       placeholder="Cari nama calon, telepon, alamat...">
            </div>
        </div>

        <div class="col-6 col-lg-2">
            <select name="status" id="filter-status" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                <option value="SUBMITTED" selected>Menunggu Verifikasi</option>
                <option value="NEEDS_REVISION">Perlu Perbaikan</option>
                <option value="APPROVED">Disetujui</option>
                <option value="REJECTED">Ditolak</option>
            </select>
        </div>

        <div class="col-6 col-lg-2">
            <select name="marketing_user_id" id="filter-marketing" class="form-select form-select-sm">
                <option value="">Semua Marketing</option>
                @foreach($marketingUsers as $mUser)
                    <option value="{{ $mUser->id }}">{{ $mUser->name }} ({{ $mUser->pop?->code }})</option>
                @endforeach
            </select>
        </div>

        @if(auth()->user()->isSuperAdmin())
        <div class="col-6 col-lg-2">
            <select name="pop_id" id="filter-pop" class="form-select form-select-sm">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}">{{ $pop->code }} - {{ $pop->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="col-6 {{ auth()->user()->isSuperAdmin() ? 'col-lg-2' : 'col-lg-3' }}">
            <div class="input-group input-group-sm">
                <input type="date" class="form-control form-control-sm" name="start_date" id="filter-start-date" title="Tanggal Mulai">
                <span class="input-group-text">-</span>
                <input type="date" class="form-control form-control-sm" name="end_date" id="filter-end-date" title="Tanggal Sampai">
            </div>
        </div>

        <div class="col-6 col-lg-1 ms-lg-auto text-end">
            <x-btn variant="neutral" id="btn-reset-admin-app-filter" class="w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
    <table id="table-admin-applications" class="table table-hover align-middle mb-0 w-100">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th class="text-center">POP</th>
                <th>Marketing</th>
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

{{-- ── Offcanvas Detail & Review ────────────────────────────────── --}}
<x-offcanvas-form id="offcanvas-review-application" formId="form-review-application" title="Detail Pendaftaran" titleId="review-title" subtitleId="review-subtitle">
    <input type="hidden" id="review-app-id">

    <!-- Duplicate Warnings Alert -->
            <div id="duplicate-warning-container" class="alert alert-warning mb-3 d-none">
                <div class="d-flex align-items-start gap-2">
                    <div class="w-100">
                        <strong class="d-block mb-1">Peringatan Kemungkinan Duplikat:</strong>
                        <ul class="mb-0 ps-3 small" id="duplicate-warning-list"></ul>
                    </div>
                </div>
            </div>

            <!-- Approved Customer Banner -->
            <div id="approved-customer-container" class="alert alert-success mb-3 d-none">
                <div class="d-flex align-items-center gap-2">
                    <div>
                        <strong>Pendaftaran Telah Disetujui</strong>
                        <div class="small mt-1">
                            Pelanggan aktif: <a href="#" id="approved-customer-link" class="fw-bold text-decoration-none"></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Review Note Banner if exists -->
            <div id="existing-review-note-container" class="alert alert-secondary mb-3 d-none">
                <strong class="d-block small text-muted" id="existing-review-note-title">Catatan Verifikator:</strong>
                <div id="existing-review-note-text" class="mt-1 small"></div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-semibold">POP</label>
                    <input type="text" id="detail-pop" class="form-control form-control-sm bg-light" readonly>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Marketing Pengaju</label>
                    <input type="text" id="detail-marketing" class="form-control form-control-sm bg-light" readonly>
                </div>

                <div class="col-12">
                    <label for="detail-name" class="form-label small fw-semibold">
                        Nama Calon Pelanggan <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="name" id="detail-name" class="form-control form-control-sm" required>
                </div>

                <div class="col-12">
                    <label for="detail-phone" class="form-label small fw-semibold">
                        Nomor Telepon <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="phone" id="detail-phone" class="form-control form-control-sm" required>
                </div>

                <div class="col-12">
                    <label for="detail-address" class="form-label small fw-semibold">
                        Alamat Pemasangan <span class="text-danger">*</span>
                    </label>
                    <textarea name="address" id="detail-address" class="form-control form-control-sm" rows="3" required></textarea>
                </div>

                <!-- Koordinat -->
                <div class="col-12">
                    <label for="detail-coordinates" class="form-label small fw-semibold">Koordinat Lokasi (Latitude, Longitude)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="coordinates" id="detail-coordinates" class="form-control form-control-sm" placeholder="lat, lng">
                        <x-btn variant="neutral" href="#" target="_blank" rel="noopener noreferrer" id="detail-map-link" class="d-none">Peta</x-btn>
                    </div>
                </div>

                <div class="col-12">
                    <label for="detail-package" class="form-label small fw-semibold">
                        Paket Layanan <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="package" id="detail-package" class="form-control form-control-sm" required>
                </div>

                <div class="col-12">
                    <label for="detail-billing-type" class="form-label small fw-semibold">Tipe Tagihan</label>
                    <select name="billing_type" id="detail-billing-type" class="form-select form-select-sm">
                        <option value="Bulanan">Bulanan</option>
                        <option value="Tahunan">Tahunan</option>
                        <option value="Pascabayar">Pascabayar</option>
                        <option value="Prabayar">Prabayar</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="detail-notes" class="form-label small fw-semibold">Catatan Marketing / Survei</label>
                    <textarea name="notes" id="detail-notes" class="form-control form-control-sm" rows="2"></textarea>
                </div>

                <!-- KTP File View -->
                <div class="col-12">
                    <label class="form-label small fw-semibold">Berkas KTP</label>
                    <div id="detail-ktp-container">
                        <div class="p-3 border rounded bg-light text-center" id="detail-no-ktp">
                            <span class="text-muted small">Tidak ada berkas KTP terlampir.</span>
                        </div>
                        <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between d-none" id="detail-has-ktp">
                            <div class="d-flex align-items-center gap-2">
                                <div>
                                    <div class="fw-semibold small">Foto KTP Calon Pelanggan</div>
                                    <span class="text-muted small">Tersimpan di storage privat terenkripsi</span>
                                </div>
                            </div>
                            <x-btn variant="export" href="#" target="_blank" rel="noopener noreferrer" id="detail-ktp-link">Buka KTP</x-btn>
                        </div>
                    </div>
                </div>
            </div>

    <x-slot:footer>
        <x-btn variant="neutral" data-bs-dismiss="offcanvas">Tutup</x-btn>
        <div id="review-actions-group" class="d-flex gap-2">
            <x-btn variant="danger" id="btn-reject-app">Tolak</x-btn>
            <x-btn variant="warning" id="btn-revision-app">Perbaikan</x-btn>
            <x-btn variant="approve" id="btn-approve-app">Setujui</x-btn>
        </div>
    </x-slot:footer>
</x-offcanvas-form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const offcanvasEl = document.getElementById('offcanvas-review-application');
    const offcanvas = {
        show: () => window.showOffcanvas(offcanvasEl),
        hide: () => window.hideOffcanvas(offcanvasEl),
    };
    const form = document.getElementById('form-review-application');
    const appIdInput = document.getElementById('review-app-id');
    const duplicateContainer = document.getElementById('duplicate-warning-container');
    const duplicateList = document.getElementById('duplicate-warning-list');
    const approvedContainer = document.getElementById('approved-customer-container');
    const approvedLink = document.getElementById('approved-customer-link');
    const existingNoteContainer = document.getElementById('existing-review-note-container');
    const existingNoteTitle = document.getElementById('existing-review-note-title');
    const existingNoteText = document.getElementById('existing-review-note-text');
    const reviewActionsGroup = document.getElementById('review-actions-group');
    const mapLink = document.getElementById('detail-map-link');
    const coordInput = document.getElementById('detail-coordinates');
    const hasKtpWrapper = document.getElementById('detail-has-ktp');
    const noKtpWrapper = document.getElementById('detail-no-ktp');
    const ktpLink = document.getElementById('detail-ktp-link');

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

    const table = window.initServerTable('#table-admin-applications', {
        ajaxUrl: '{{ route('customer-applications.index') }}',
        filterForm: '#filter-customer-applications-form',
        searchInput: '#filter-admin-app-search',
        resetButton: '#btn-reset-admin-app-filter',
        pageLength: 15,
        order: [[0, 'desc']],
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'pop_code', name: 'pop.code', className: 'fw-semibold text-center' },
            { data: 'marketing_name', name: 'marketingUser.name' },
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
                    return window.btnIconHtml('view', { class: 'btn-review-app', 'data-id': row.id, title: 'Periksa Pendaftaran' });
                }
            }
        ]
    });

    $(document).on('click', '.btn-review-app', async function () {
        const id = $(this).data('id');
        form.reset();
        updateMapLink();

        try {
            const data = await window.apiRequest(`{{ url('customer-applications') }}/${id}`, {});

            appIdInput.value = data.id;
            document.getElementById('review-title').textContent = `Pendaftaran #${data.id} - ${data.name}`;
            document.getElementById('review-subtitle').textContent = `Diajukan pada ${data.created_at} oleh ${data.marketing_name || '-'}`;
            document.getElementById('detail-pop').value = `${data.pop_code} - ${data.pop_name}`;
            document.getElementById('detail-marketing').value = `${data.marketing_name} (${data.marketing_phone || '-'})`;
            document.getElementById('detail-name').value = data.name || '';
            document.getElementById('detail-phone').value = data.phone || '';
            document.getElementById('detail-address').value = data.address || '';
            document.getElementById('detail-coordinates').value = data.coordinates || '';
            document.getElementById('detail-package').value = data.package || '';
            document.getElementById('detail-billing-type').value = data.billing_type || 'Bulanan';
            document.getElementById('detail-notes').value = data.notes || '';
            updateMapLink();

            // Duplicate Warnings
            if (data.duplicate_warnings && data.duplicate_warnings.length > 0) {
                duplicateContainer.classList.remove('d-none');
                duplicateList.innerHTML = data.duplicate_warnings.map(w => `<li>${w}</li>`).join('');
            } else {
                duplicateContainer.classList.add('d-none');
                duplicateList.innerHTML = '';
            }

            // KTP preview
            if (data.has_ktp && data.ktp_url) {
                hasKtpWrapper.classList.remove('d-none');
                noKtpWrapper.classList.add('d-none');
                ktpLink.href = data.ktp_url;
            } else {
                hasKtpWrapper.classList.add('d-none');
                noKtpWrapper.classList.remove('d-none');
            }

            // Existing note
            if (data.review_note) {
                existingNoteContainer.classList.remove('d-none');
                existingNoteTitle.textContent = data.status === 'NEEDS_REVISION' ? 'Catatan Perbaikan Terakhir:' : 'Alasan Penolakan:';
                existingNoteText.textContent = data.review_note;
            } else {
                existingNoteContainer.classList.add('d-none');
            }

            // Status handling
            if (data.status === 'APPROVED') {
                approvedContainer.classList.remove('d-none');
                approvedLink.textContent = `${data.customer_code} (${data.name})`;
                approvedLink.href = `{{ url('customers') }}/${data.customer_id}`;
                reviewActionsGroup.classList.add('d-none');
                form.querySelectorAll('input, select, textarea').forEach(el => el.setAttribute('readonly', 'readonly'));
            } else if (data.status === 'REJECTED') {
                approvedContainer.classList.add('d-none');
                reviewActionsGroup.classList.add('d-none');
                form.querySelectorAll('input, select, textarea').forEach(el => el.setAttribute('readonly', 'readonly'));
            } else {
                approvedContainer.classList.add('d-none');
                reviewActionsGroup.classList.remove('d-none');
                form.querySelectorAll('input, select, textarea').forEach(el => {
                    if (el.id !== 'detail-pop' && el.id !== 'detail-marketing') {
                        el.removeAttribute('readonly');
                    }
                });
            }

            offcanvas.show();
        } catch (err) {
            window.showToast?.(err.message, 'error');
        }
    });

    // 1. Approve
    document.getElementById('btn-approve-app')?.addEventListener('click', async function () {
        const id = appIdInput.value;
        if (!id) return;

        const confirm = await window.confirmAction?.({
            title: 'Setujui Pendaftaran?',
            text: 'Pelanggan baru dan kode JCN akan langsung dibuat di database.',
            confirmButtonText: 'Ya, Setujui',
            confirmButtonClass: 'btn btn-success',
            icon: 'question'
        });

        if (!confirm || !confirm.isConfirmed) return;

        try {
            const result = await window.apiRequest(`{{ url('customer-applications') }}/${id}/approve`, {
                method: 'POST',
                body: Object.fromEntries(new FormData(form)),
            });

            offcanvas.hide();
            window.toast('success', result.message || 'Pendaftaran disetujui.');
            table.ajax.reload(null, false);
        } catch (err) {
            window.toast('error', err.message);
        }
    });

    // 2. Request Revision
    document.getElementById('btn-revision-app')?.addEventListener('click', async function () {
        const id = appIdInput.value;
        if (!id) return;

        const { value: note, isConfirmed } = await window.promptReason({
            title: 'Minta Perbaikan Pendaftaran',
            inputLabel: 'Catatan Perbaikan untuk Marketing (Wajib)',
            inputPlaceholder: 'Tuliskan bagian data yang perlu diperbaiki...',
            confirmButtonText: 'Kirim Permintaan',
            cancelButtonText: 'Batal',
            confirmButtonClass: 'btn btn-warning text-dark',
            requiredMessage: 'Catatan perbaikan wajib diisi!',
        });

        if (!isConfirmed || !note) return;

        try {
            const result = await window.apiRequest(`{{ url('customer-applications') }}/${id}/request-revision`, {
                method: 'POST',
                body: { review_note: note.trim() },
            });

            offcanvas.hide();
            window.toast('success', result.message || 'Permintaan perbaikan terkirim.');
            table.ajax.reload(null, false);
        } catch (err) {
            window.toast('error', err.message);
        }
    });

    // 3. Reject
    document.getElementById('btn-reject-app')?.addEventListener('click', async function () {
        const id = appIdInput.value;
        if (!id) return;

        const { value: reason, isConfirmed } = await window.promptReason({
            title: 'Tolak Pendaftaran?',
            text: 'Berkas KTP yang terlampir akan dihapus dari server demi keamanan data.',
            inputLabel: 'Alasan Penolakan (Wajib)',
            inputPlaceholder: 'Tuliskan alasan penolakan pendaftaran...',
            confirmButtonText: 'Tolak Pendaftaran',
            cancelButtonText: 'Batal',
            confirmButtonClass: 'btn btn-danger',
            icon: 'warning',
            requiredMessage: 'Alasan penolakan wajib diisi!',
        });

        if (!isConfirmed || !reason) return;

        try {
            const result = await window.apiRequest(`{{ url('customer-applications') }}/${id}/reject`, {
                method: 'POST',
                body: { review_note: reason.trim() },
            });

            offcanvas.hide();
            window.toast('success', result.message || 'Pendaftaran telah ditolak.');
            table.ajax.reload(null, false);
        } catch (err) {
            window.toast('error', err.message);
        }
    });
});
</script>
@endpush
