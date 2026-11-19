@extends('layouts.app')

@section('title', 'Manajemen Staf')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Manajemen Staf & Hak Akses" subtitle="Kelola akun pengguna, peran tim lapangan, teknisi, marketing, dan admin POP">
    <x-slot:actions>
        <x-btn variant="primary"     id="btn-open-create-staff">
            Tambah Staf
        </x-btn>
    </x-slot:actions>
</x-page-header>

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<x-filter-card>
    <form id="filter-staff-form" class="row g-2 align-items-center">
        <div class="col-12 col-lg-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"></span>
                <input type="search" id="filter-staff-search" name="search" class="form-control form-control-sm"
                       placeholder="Cari nama, email, atau telepon...">
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <select name="role" id="filter-staff-role" class="form-select form-select-sm">
                <option value="">Semua Peran</option>
                @foreach($roles as $role)
                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-lg-3">
            <select name="pop_id" id="filter-staff-pop" class="form-select form-select-sm">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}">{{ $pop->code }} - {{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-lg-2 ms-lg-auto text-end">
            <x-btn variant="neutral" id="btn-reset-staff-filter" class="w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
    <table id="staff-table" class="table table-hover align-middle mb-0 w-100">
        <thead>
            <tr>
                <th>Nama Staf</th>
                <th>Email</th>
                <th>Telepon</th>
                <th>Peran</th>
                <th>POP</th>
                <th class="text-center">Status</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</x-table-card>

{{-- ── Offcanvas Form Staf (Tambah & Ubah) ──────────────────────── --}}
@include('staff.partials.offcanvas')

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('staff_role');
    const popGroup   = document.getElementById('staff_pop_group');
    const popSelect  = document.getElementById('staff_pop_id');
    const popStar    = document.getElementById('staff_pop_required_star');
    const activeCheck= document.getElementById('staff_is_active');
    const selfHelp   = document.getElementById('staff_self_help');

    function syncPopVisibility() {
        const selectedOpt = roleSelect.options[roleSelect.selectedIndex];
        const requiresPop = selectedOpt ? selectedOpt.getAttribute('data-requires-pop') === '1' : false;

        if (requiresPop) {
            popGroup.style.display = 'block';
            popSelect.setAttribute('required', 'required');
            popStar.style.display = 'inline';
        } else {
            popGroup.style.display = 'block';
            popSelect.removeAttribute('required');
            popStar.style.display = 'none';
            if (roleSelect.value === 'super_admin' || roleSelect.value === 'noc') {
                popSelect.value = '';
                popGroup.style.display = 'none';
            }
        }
    }

    roleSelect.addEventListener('change', syncPopVisibility);

    // Helper modal Swal Tautan Set Password
    function showPasswordLinkModal(url, staffName) {
        if (typeof window.showPasswordLinkModal === 'function') {
            return window.showPasswordLinkModal(url, staffName);
        }
    }

    // 1. Inisialisasi DataTable Staf Server-Side
    const table = window.initServerTable('#staff-table', {
        ajaxUrl: '{{ route('staff.index') }}',
        filterForm: '#filter-staff-form',
        searchInput: '#filter-staff-search',
        resetButton: '#btn-reset-staff-filter',
        pageLength: 15,
        columns: [
            {
                data: 'name',
                name: 'name',
                render: function (data, type, row) {
                    const selfBadge = row.is_self ? ' <span class="badge bg-light text-dark border">Anda</span>' : '';
                    return `<span class="fw-semibold text-dark">${window.escapeHtml(data)}</span>${selfBadge}`;
                }
            },
            {
                data: 'email',
                name: 'email',
                render: function (data) {
                    return `<span class="font-monospace small text-muted">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'phone',
                name: 'phone',
                render: function (data) {
                    return `<span class="small">${window.escapeHtml(data || '-')}</span>`;
                }
            },
            {
                data: 'role_label',
                name: 'role',
                render: function (data, type, row) {
                    return `<span class="badge bg-${row.role_badge}">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'pop_name',
                name: 'pop_id',
                render: function (data) {
                    return `<span class="small text-secondary">${window.escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'is_active',
                name: 'is_active',
                className: 'text-center',
                render: function (data) { return window.statusBadge('active_status', data ? 'ACTIVE' : 'INACTIVE'); }
            },
            {
                data: null,
                orderable: false,
                className: 'text-end text-nowrap',
                render: function (data, type, row) {
                    return `<div class="d-flex justify-content-end gap-1">
                        ${window.btnIconHtml('edit', { class: 'btn-edit-staff', 'data-id': row.id, title: 'Ubah Staf' })}
                    </div>`;
                }
            }
        ]
    });

    // 2. Inisialisasi CRUD Offcanvas Staf
    const crudStaff = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-staff',
        storeUrl: '{{ route('staff.store') }}',
        baseUrl: '{{ url('staff') }}',
        createTitle: 'Tambah Akun Staf',
        editTitle: 'Ubah Data Staf',
        table: table,
        onOpenCreate: function () {
            roleSelect.disabled = false;
            activeCheck.disabled = false;
            selfHelp.style.display = 'none';
            syncPopVisibility();
        },
        onOpenEdit: function (form, record) {
            syncPopVisibility();
            if (record.is_self) {
                roleSelect.disabled = true;
                activeCheck.disabled = true;
                selfHelp.style.display = 'block';
            } else {
                roleSelect.disabled = false;
                activeCheck.disabled = false;
                selfHelp.style.display = 'none';
            }
        },
        onSuccess: function (resJson, isEditMode) {
            if (resJson.set_password_url) {
                const name = resJson.data ? resJson.data.name : '';
                showPasswordLinkModal(resJson.set_password_url, name);
            }
        }
    });

    // Tombol Buka Tambah Staf
    document.getElementById('btn-open-create-staff')?.addEventListener('click', function () {
        crudStaff.openCreate();
    });

    // Tombol Ubah Staf (Event Delegation)
    $('#staff-table').on('click', '.btn-edit-staff', function () {
        const id = $(this).data('id');
        const url = $(this).data('url');
        crudStaff.openEdit(id, url);
    });

    // Tombol Reset Akses (Event Delegation)
    $('#staff-table').on('click', '.btn-reset-access', async function () {
        const url = $(this).data('url');
        const name = $(this).data('name');

        const result = await window.confirmAction({
            title: 'Reset Akses Staf?',
            text: `Buat tautan set-password baru untuk "${name}"? Tautan sebelumnya akan hangus.`,
            confirmButtonText: 'Ya, Buat Tautan',
            cancelButtonText: 'Batal',
            confirmButtonClass: 'btn btn-warning',
        });

        if (!result.isConfirmed) return;

        try {
            const data = await window.apiRequest(url, { method: 'POST' });

            if (data.set_password_url) {
                showPasswordLinkModal(data.set_password_url, name);
                table.ajax.reload(null, false);
            }
        } catch (err) {
            window.toast('error', err.message || 'Terjadi kesalahan sistem.');
        }
    });
});
</script>
@endpush

