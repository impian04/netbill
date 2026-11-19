@extends('layouts.app')

@section('title', 'Data Marketing & Pembayaran')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Data Rekening Marketing" subtitle="Kelola akun marketing dan informasi rekening/e-wallet untuk penyaluran fee komisi" />

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<x-filter-card>
    <form id="filter-profiles-form" class="row g-2 align-items-center">
        <div class="col-12 {{ auth()->user()->isSuperAdmin() ? 'col-lg-4' : 'col-lg-6' }}">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"></span>
                <input type="search" id="filter-profile-search" name="search" class="form-control form-control-sm"
                       placeholder="Cari nama, email, atau telepon...">
            </div>
        </div>

        @if(auth()->user()->isSuperAdmin())
        <div class="col-6 col-lg-3">
            <select name="pop_id" id="filter-profile-pop" class="form-select form-select-sm">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}">{{ $pop->code }} - {{ $pop->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="col-6 col-lg-2 ms-lg-auto text-end">
            <x-btn variant="neutral" id="btn-reset-profile-filter" class="w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
    <table id="table-marketing-profiles" class="table table-hover align-middle mb-0 w-100">
        <thead>
            <tr>
                <th>Nama Marketing</th>
                <th class="text-center">POP</th>
                <th>No. Telepon</th>
                <th>Metode</th>
                <th>Penyedia</th>
                <th>No. Rekening</th>
                <th>Atas Nama</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</x-table-card>

{{-- ── Offcanvas Data Pembayaran ────────────────────────────────── --}}
<x-offcanvas-form id="offcanvas-payout" formId="form-payout" title="Data Rekening Pembayaran" titleId="offcanvasPayoutTitle" subtitleId="payout-subtitle" method="PUT" submitId="btn-save-payout" submitText="Simpan Data Pembayaran">
    <input type="hidden" id="payout-user-id">

    <div class="row g-3">
                <div class="col-12">
                    <label for="payout-method" class="form-label small fw-semibold">
                        Metode Penyaluran Fee <span class="text-danger">*</span>
                    </label>
                    <select name="payout_method" id="payout-method" class="form-select form-select-sm" required>
                        <option value="BANK">Transfer Bank</option>
                        <option value="EWALLET">E-Wallet (GoPay, OVO, DANA, dll)</option>
                        <option value="LAINNYA">Lainnya / Tunai</option>
                    </select>
                    <div class="invalid-feedback" id="err-payout_method"></div>
                </div>

                <div class="col-12">
                    <label for="payout-provider" class="form-label small fw-semibold">
                        Nama Bank / Penyedia E-Wallet <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="provider_name" id="payout-provider" class="form-control form-control-sm" placeholder="Contoh: BCA, BRI, Mandiri, GoPay" required>
                    <div class="invalid-feedback" id="err-provider_name"></div>
                </div>

                <div class="col-12">
                    <label for="payout-account-number" class="form-label small fw-semibold" id="label-account-number">
                        Nomor Rekening / No. HP E-Wallet <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="account_number" id="payout-account-number" class="form-control form-control-sm" placeholder="Masukkan nomor rekening utuh">
                    <div class="form-text small text-muted fs-xs" id="help-account-number">
                        Nomor rekening disimpan terenkripsi di database. Di layar hanya 4 digit terakhir yang ditampilkan.
                    </div>
                    <div class="invalid-feedback" id="err-account_number"></div>
                </div>

                <div class="col-12">
                    <label for="payout-account-holder" class="form-label small fw-semibold">
                        Nama Pemilik Rekening / Akun <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="account_holder" id="payout-account-holder" class="form-control form-control-sm" placeholder="Nama sesuai buku tabungan / akun e-wallet" required>
                    <div class="invalid-feedback" id="err-account_holder"></div>
                </div>

                <div class="col-12">
                    <label for="payout-notes" class="form-label small fw-semibold">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" id="payout-notes" class="form-control form-control-sm" rows="3" placeholder="Informasi kantor cabang, catatan verifikasi, dll"></textarea>
                </div>
    </div>
</x-offcanvas-form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const offcanvasEl = document.getElementById('offcanvas-payout');
    const offcanvas = {
        show: () => window.showOffcanvas(offcanvasEl),
        hide: () => window.hideOffcanvas(offcanvasEl),
    };
    const form = document.getElementById('form-payout');
    const userIdInput = document.getElementById('payout-user-id');
    const submitBtn = document.getElementById('btn-save-payout');

    const table = window.initServerTable('#table-marketing-profiles', {
        ajaxUrl: '{{ route('marketing-profiles.index') }}',
        filterForm: '#filter-profiles-form',
        searchInput: '#filter-profile-search',
        resetButton: '#btn-reset-profile-filter',
        pageLength: 15,
        order: [[0, 'asc']],
        columns: [
            { data: 'name', name: 'name', className: 'fw-semibold text-dark' },
            { data: 'pop_code', name: 'pop.code', className: 'text-center' },
            { data: 'phone', name: 'phone' },
            { data: 'payout_method', name: 'marketingProfile.payout_method' },
            { data: 'provider_name', name: 'marketingProfile.provider_name' },
            { data: 'masked_account', name: 'marketingProfile.account_number' },
            { data: 'account_holder', name: 'marketingProfile.account_holder' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end',
                render: function (data, type, row) { return window.btnIconHtml('edit', { class: 'btn-edit-payout', 'data-id': row.id, title: 'Ubah Data Rekening' }); }
            }
        ]
    });

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => { el.textContent = ''; });
    }

    $(document).on('click', '.btn-edit-payout', async function () {
        const id = $(this).data('id');
        clearErrors();
        form.reset();

        try {
            const data = await window.apiRequest(`{{ url('marketing-profiles') }}/${id}`);

            userIdInput.value = data.user.id;
            document.getElementById('payout-subtitle').textContent = `${data.user.name} (POP ${data.user.pop_code})`;
            document.getElementById('payout-method').value = data.profile.payout_method || 'BANK';
            document.getElementById('payout-provider').value = data.profile.provider_name || '';
            document.getElementById('payout-account-holder').value = data.profile.account_holder || '';
            document.getElementById('payout-notes').value = data.profile.notes || '';

            const accInput = document.getElementById('payout-account-number');
            if (data.profile.has_profile && data.profile.masked_account_number) {
                accInput.value = '';
                accInput.placeholder = `Tersimpan: ${data.profile.masked_account_number} (Kosongkan jika tidak diubah)`;
                accInput.removeAttribute('required');
            } else {
                accInput.value = '';
                accInput.placeholder = 'Masukkan nomor rekening utuh';
                accInput.setAttribute('required', 'required');
            }

            offcanvas.show();
        } catch (err) {
            window.toast('error', err.message || 'Gagal memuat profil pembayaran.');
        }
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearErrors();

        const id = userIdInput.value;
        const formData = new FormData(form);

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';

        try {
            const result = await window.apiRequest(`{{ url('marketing-profiles') }}/${id}`, {
                method: 'POST',
                body: formData,
            });

            offcanvas.hide();
            window.toast('success', result.message || 'Data rekening berhasil disimpan.');
            table.ajax.reload(null, false);
        } catch (err) {
            if (err.status === 422 && err.data?.errors) {
                let firstInvalidEl = null;
                for (const [key, msgs] of Object.entries(err.data.errors)) {
                    const input = form.querySelector(`[name="${key}"]`);
                    const errEl = document.getElementById(`err-${key}`);
                    if (input) {
                        input.classList.add('is-invalid');
                        if (!firstInvalidEl) firstInvalidEl = input;
                    }
                    if (errEl) errEl.textContent = msgs[0];
                }
                if (firstInvalidEl) firstInvalidEl.focus();
                window.toast('warning', 'Mohon periksa data form.');
            } else {
                window.toast('error', err.message || 'Gagal menyimpan data rekening.');
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Simpan Data Pembayaran';
        }
    });
});
</script>
@endpush
