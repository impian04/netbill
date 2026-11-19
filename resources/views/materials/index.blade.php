@extends('layouts.app')

@section('title', 'Master Barang')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Master Data Barang" subtitle="Kelola katalog barang, material jaringan, dan unit inventaris per wilayah POP">
    <x-slot:actions>
        <x-btn variant="export" id="btn-open-import-material">Import Barang</x-btn>
        <x-btn variant="primary" id="btn-open-create-material">Tambah Barang</x-btn>
    </x-slot:actions>
</x-page-header>

{{-- ── Alert Hasil Import Rinci Jika Ada ───────────────────────── --}}
@if(session('import_result_details') && count(session('import_result_details')) > 0)
    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center gap-2 mb-2">
            <strong>Catatan Rincian Hasil Import:</strong>
        </div>
        <ul class="mb-0 small ps-3" style="max-height: 180px; overflow-y: auto;">
            @foreach(session('import_result_details') as $detail)
                <li>{{ $detail }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<x-filter-card>
    <form id="filter-materials-form" class="row g-2 align-items-center">
        <div class="col-12 {{ auth()->user()->isSuperAdmin() ? 'col-lg-4' : 'col-lg-5' }}">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"></span>
                <input type="search" id="filter-material-search" name="search" class="form-control form-control-sm"
                       placeholder="Cari nama barang...">
            </div>
        </div>

        @if(auth()->user()->isSuperAdmin())
        <div class="col-6 col-lg-3">
            <select name="pop_id" id="filter-pop" class="form-select form-select-sm">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}">{{ $pop->code }} - {{ $pop->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="col-6 col-lg-2">
            <select name="unit" id="filter-unit" class="form-select form-select-sm">
                <option value="">Semua Satuan</option>
                @foreach($units as $u)
                    <option value="{{ $u }}">{{ $u }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-lg-2">
            <select name="is_active" id="filter-status" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>

        <div class="col-6 {{ auth()->user()->isSuperAdmin() ? 'col-lg-1' : 'col-lg-1' }} ms-lg-auto text-end">
            <x-btn variant="neutral" id="btn-reset-material-filter" class="w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
    <table id="table-materials" class="table table-hover align-middle mb-0 w-100">
        <thead>
            <tr>
                <th>Nama Barang / Material</th>
                <th style="width: 100px;" class="text-center">Satuan</th>
                <th style="width: 80px;" class="text-center">POP</th>
                <th style="width: 100px;" class="text-center">Status</th>
                <th style="width: 110px;" class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</x-table-card>

{{-- ── Offcanvas Form Barang (Tambah & Ubah) ────────────────────── --}}
<x-offcanvas-form id="offcanvas-material" formId="form-material" title="Tambah Barang" titleId="materialTitle" subtitleId="materialSubtitle" submitId="btn-save-material" submitText="Simpan Barang">
    <div class="row g-3">
        <div class="col-12">
                    <label for="material-pop-id" class="form-label small fw-semibold">
                        POP <span class="text-danger">*</span>
                    </label>
                    @if(auth()->user()->isSuperAdmin())
                        <select name="pop_id" id="material-pop-id" class="form-select form-select-sm" required>
                            @foreach($pops as $pop)
                                <option value="{{ $pop->id }}">POP {{ $pop->code }} - {{ $pop->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="pop_id" id="material-pop-id" value="{{ auth()->user()->pop_id }}">
                        <input type="text" class="form-control form-control-sm bg-light" value="POP {{ auth()->user()->pop?->code }} - {{ auth()->user()->pop?->name }}" readonly>
                    @endif
                    <div class="invalid-feedback" id="err-pop_id"></div>
                </div>

                <div class="col-12">
                    <label for="material-name" class="form-label small fw-semibold">
                        Nama Barang <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="material-name" name="name" class="form-control form-control-sm" placeholder="Contoh: Kabel Dropcore 1 Core" required>
                    <div class="invalid-feedback" id="err-name"></div>
                </div>

                <div class="col-12">
                    <label for="material-unit" class="form-label small fw-semibold">
                        Satuan Unit <span class="text-danger">*</span>
                    </label>
                    <select name="unit" id="material-unit" class="form-select form-select-sm" required>
                        <option value="">Pilih Satuan</option>
                        @foreach($units as $u)
                            <option value="{{ $u }}">{{ $u }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Daftar satuan standar: meter, pcs, unit, roll, set.</div>
                    <div class="invalid-feedback" id="err-unit"></div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="material-is-active" name="is_active" value="1" checked>
                        <label class="form-check-label small fw-semibold" for="material-is-active">Status Aktif</label>
                    </div>
                    <div class="form-text">Barang yang dinonaktifkan tidak akan muncul pada pilihan transaksi/instalasi baru.</div>
                </div>

                <div id="material-used-warning" class="col-12 d-none">
                    <div class="alert alert-warning py-2 mb-0 d-flex gap-2">
                        <div>Barang ini sudah memiliki riwayat pemakaian dalam sistem. Barang tidak dapat dihapus, namun statusnya dapat dinonaktifkan jika tidak lagi digunakan.</div>
                    </div>
                </div>
    </div>
</x-offcanvas-form>

{{-- ── Offcanvas Import Barang ──────────────────────────────────── --}}
<x-offcanvas-form id="offcanvas-import-material" formId="form-import-material" title="Import Data Barang" titleId="importMaterialTitle" action="{{ route('master-import.upload', 'material') }}" submitText="Mulai Import">
    <div class="alert alert-info py-2 mb-3 d-flex gap-2">
                <div>
                    Format kolom template Barang: <strong>name, unit</strong>.
                    <br>Pilihan satuan unit valid: <code>meter, pcs, unit, roll, set</code>.
                    <br>Maksimal 2.000 baris dalam satu transaksi. Duplikat nama barang dalam POP yang sama akan dilewati secara otomatis.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Unduh Template Contoh</label>
                <div class="d-flex gap-2">
                    <x-btn variant="export" href="{{ route('master-import.template', ['type' => 'material', 'format' => 'xlsx']) }}">Template Excel (.xlsx)</x-btn>
                    <x-btn variant="neutral" href="{{ route('master-import.template', ['type' => 'material', 'format' => 'csv']) }}">Template CSV (.csv)</x-btn>
                </div>
            </div>

            <hr class="my-3">

            @if(auth()->user()->isSuperAdmin())
                <div class="mb-3">
                    <label for="import-material-pop-id" class="form-label small fw-semibold">
                        Pilih POP Target <span class="text-danger">*</span>
                    </label>
                    <select name="pop_id" id="import-material-pop-id" class="form-select form-select-sm" required>
                        @foreach($pops as $pop)
                            <option value="{{ $pop->id }}">POP {{ $pop->code }} - {{ $pop->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="mb-3">
                <label for="import-material-file" class="form-label small fw-semibold">
                    Pilih Berkas Spreadsheet <span class="text-danger">*</span>
                </label>
                <input type="file" name="file" id="import-material-file" class="form-control form-control-sm" accept=".xlsx,.csv" required>
                <div class="form-text">Format berkas: .xlsx atau .csv (maksimal 5 MB).</div>
            </div>

    <x-slot:footer>
        <x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>
        <x-btn type="submit" variant="primary" id="btn-submit-import-material">Unggah &amp; Pratinjau</x-btn>
    </x-slot:footer>
</x-offcanvas-form>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = window.initServerTable('#table-materials', {
        ajaxUrl: '{{ route('materials.index') }}',
        filterForm: '#filter-materials-form',
        searchInput: '#filter-material-search',
        resetButton: '#btn-reset-material-filter',
        pageLength: 15,
        order: [[0, 'asc']],
        columns: [
            { data: 'name', name: 'name', className: 'fw-semibold text-dark' },
            {
                data: 'unit',
                name: 'unit',
                className: 'text-center',
                render: function (data) {
                    return `<span class="badge bg-light text-dark border font-monospace">${data}</span>`;
                }
            },
            { data: 'pop_code', name: 'pop.code', className: 'text-center' },
            {
                data: 'is_active',
                name: 'is_active',
                className: 'text-center',
                render: function (data) { return window.statusBadge('active_status', data ? 'ACTIVE' : 'INACTIVE'); }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end',
                render: function (data, type, row) {
                    const editBtn = window.btnIconHtml('edit', {
                        'class': 'btn-edit-material',
                        'data-id': row.id,
                        'title': 'Ubah Barang'
                    });
                    let deleteBtn = '';
                    if (row.is_used) {
                        deleteBtn = window.btnIconHtml('delete', {
                            'title': 'Tidak dapat dihapus karena terpakai',
                            'disabled': 'disabled'
                        });
                    } else {
                        deleteBtn = window.btnIconHtml('delete', {
                            'class': 'btn-delete-material',
                            'data-id': row.id,
                            'data-name': row.name,
                            'data-url': row.urls.delete,
                            'title': 'Hapus Barang'
                        });
                    }

                    return `<div class="btn-group btn-group-sm">${editBtn}${deleteBtn}</div>`;
                }
            }
        ]
    });

    // CRUD Offcanvas Barang
    const crudMaterial = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-material',
        storeUrl: '{{ route('materials.store') }}',
        baseUrl: '{{ url('materials') }}',
        createTitle: 'Tambah Barang',
        editTitle: 'Ubah Data Barang',
        table: table,
        onOpenCreate: function (form) {
            document.getElementById('material-used-warning').classList.add('d-none');
            document.getElementById('material-is-active').checked = true;
        },
        onOpenEdit: function (form, record) {
            if (record.is_used) {
                document.getElementById('material-used-warning').classList.remove('d-none');
            } else {
                document.getElementById('material-used-warning').classList.add('d-none');
            }
            document.getElementById('material-is-active').checked = !!record.is_active;
        }
    });

    document.getElementById('btn-open-create-material')?.addEventListener('click', function () {
        crudMaterial.openCreate();
    });

    $(document).on('click', '.btn-edit-material', function () {
        const id = $(this).data('id');
        crudMaterial.openEdit(id, `{{ url('materials') }}/${id}`);
    });

    $(document).on('click', '.btn-delete-material', async function () {
        const url = $(this).data('url');
        const name = $(this).data('name');

        const confirm = await window.confirmDialog({
            title: 'Hapus Barang?',
            text: `Hapus data barang "${name}"? Tindakan ini tidak dapat dibatalkan.`,
            confirmButtonText: 'Ya, Hapus',
            confirmButtonClass: 'btn btn-danger',
        });

        if (!confirm || !confirm.isConfirmed) return;

        try {
            const res = await window.apiRequest(url, { method: 'DELETE' });
            window.toast('success', res.message || 'Barang berhasil dihapus.');
            table.ajax.reload(null, false);
        } catch (err) {
            window.toast('warning', err.message || 'Gagal menghapus barang.');
        }
    });

    // Import Offcanvas Trigger
    document.getElementById('btn-open-import-material')?.addEventListener('click', function () {
        window.showOffcanvas('#offcanvas-import-material');
    });

    document.getElementById('form-import-material')?.addEventListener('submit', function () {
        const btn = document.getElementById('btn-submit-import-material');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mengunggah berkas...';
    });
});
</script>
@endpush

