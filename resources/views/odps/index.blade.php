@extends('layouts.app')

@section('title', 'Master ODP')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Master Data ODP" subtitle="Kelola Optical Distribution Point (ODP) dan relasinya dengan ODC induk">
    <x-slot:actions>
        <x-btn variant="export" id="btn-open-import-odp">Import ODP</x-btn>
        <x-btn variant="primary" id="btn-open-create-odp">Tambah ODP</x-btn>
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
    <form id="filter-odps-form" class="row g-2 align-items-center">
        <div class="col-12 {{ auth()->user()->isSuperAdmin() ? 'col-lg-3' : 'col-lg-4' }}">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"></span>
                <input type="search" id="filter-odp-search" name="search" class="form-control form-control-sm"
                       placeholder="Cari nama ODP / ODC...">
            </div>
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

        <div class="col-6 col-lg-3">
            <select name="odc_id" id="filter-odc" class="form-select form-select-sm">
                <option value="">Semua ODC</option>
                @foreach($odcs as $odc)
                    <option value="{{ $odc->id }}" data-pop-id="{{ $odc->pop_id }}">{{ $odc->name }} (POP {{ $odc->pop?->code }})</option>
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

        <div class="col-6 {{ auth()->user()->isSuperAdmin() ? 'col-lg-2' : 'col-lg-1' }} ms-lg-auto text-end">
            <x-btn variant="neutral" id="btn-reset-odp-filter" class="w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
    <table id="table-odps" class="table table-hover align-middle mb-0 w-100">
        <thead>
            <tr>
                <th>Nama ODP</th>
                <th>ODC Induk</th>
                <th style="width: 80px;" class="text-center">POP</th>
                <th>Koordinat Lokasi</th>
                <th style="width: 120px;" class="text-center">Pelanggan</th>
                <th style="width: 100px;" class="text-center">Status</th>
                <th style="width: 110px;" class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</x-table-card>

{{-- ── Offcanvas Form ODP (Tambah & Ubah) ───────────────────────── --}}
<x-offcanvas-form id="offcanvas-odp" formId="form-odp" title="Tambah ODP" titleId="odpTitle" subtitleId="odpSubtitle" submitId="btn-save-odp" submitText="Simpan ODP">
    <div class="row g-3">
        <div class="col-12">
                    <label for="odp-pop-id" class="form-label small fw-semibold">
                        POP <span class="text-danger">*</span>
                    </label>
                    @if(auth()->user()->isSuperAdmin())
                        <select name="pop_id" id="odp-pop-id" class="form-select form-select-sm" required>
                            @foreach($pops as $pop)
                                <option value="{{ $pop->id }}">POP {{ $pop->code }} - {{ $pop->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="pop_id" id="odp-pop-id" value="{{ auth()->user()->pop_id }}">
                        <input type="text" class="form-control form-control-sm bg-light" value="POP {{ auth()->user()->pop?->code }} - {{ auth()->user()->pop?->name }}" readonly>
                    @endif
                    <div class="invalid-feedback" id="err-pop_id"></div>
                </div>

                <div class="col-12">
                    <label for="odp-odc-id" class="form-label small fw-semibold">
                        ODC Induk <span class="text-danger">*</span>
                    </label>
                    <select name="odc_id" id="odp-odc-id" class="form-select form-select-sm" required>
                        <option value="">Pilih ODC Induk</option>
                        @foreach($odcs as $odc)
                            <option value="{{ $odc->id }}" data-pop-id="{{ $odc->pop_id }}">{{ $odc->name }} (POP {{ $odc->pop?->code }})</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" id="err-odc_id"></div>
                </div>

                <div class="col-12">
                    <label for="odp-name" class="form-label small fw-semibold">
                        Nama ODP <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="odp-name" name="name" class="form-control form-control-sm" placeholder="Contoh: ODP-01-01" required>
                    <div class="invalid-feedback" id="err-name"></div>
                </div>

                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="odp-coordinates" class="form-label small fw-semibold mb-0">
                            Koordinat Lokasi (Latitude, Longitude)
                        </label>
                        <x-btn variant="neutral"     id="btn-get-odp-loc">
                            Lokasi Saya
                        </x-btn>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" id="odp-coordinates" name="coordinates" class="form-control form-control-sm font-monospace" placeholder="Contoh: 0.5071, 101.4478">
                        <x-btn variant="neutral" href="#" target="_blank" rel="noopener noreferrer"   id="btn-open-odp-map" title="Buka di Google Maps">
                            Peta
                        </x-btn>
                    </div>
                    <input type="hidden" name="latitude" id="odp-latitude">
                    <input type="hidden" name="longitude" id="odp-longitude">
                    <div class="form-text">Derajat desimal: lat (-11.5 s/d 6.5), lng (94.0 s/d 141.5).</div>
                    <div class="invalid-feedback" id="err-latitude"></div>
                    <div class="invalid-feedback" id="err-longitude"></div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="odp-is-active" name="is_active" value="1" checked>
                        <label class="form-check-label small fw-semibold" for="odp-is-active">Status Aktif</label>
                    </div>
                    <div class="form-text">ODP yang dinonaktifkan tidak akan muncul pada pilihan pendaftaran pelanggan baru.</div>
                </div>

                <div id="odp-used-warning" class="col-12 d-none">
                    <div class="alert alert-warning py-2 mb-0 d-flex gap-2">
                        <div>ODP ini sedang digunakan oleh pelanggan. ODP tidak dapat dihapus, namun statusnya dapat dinonaktifkan jika port telah penuh atau tidak melayani sambungan baru.</div>
                    </div>
                </div>
    </div>
</x-offcanvas-form>

{{-- ── Offcanvas Import ODP ─────────────────────────────────────── --}}
<x-offcanvas-form id="offcanvas-import-odp" formId="form-import-odp" title="Import Data ODP" titleId="importOdpTitle" action="{{ route('master-import.upload', 'odp') }}">
    <div class="alert alert-info py-2 mb-3 d-flex gap-2">
                <div>
                    Format kolom template ODP: <strong>odc_name, name, latitude, longitude</strong>.
                    <br>Maksimal 2.000 baris dalam satu transaksi. ODC induk harus sudah terdaftar pada POP terkait. Duplikat nama ODP dalam POP yang sama akan dilewati secara otomatis.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Unduh Template Contoh</label>
                <div class="d-flex gap-2">
                    <x-btn variant="approve" href="{{ route('master-import.template', ['type' => 'odp', 'format' => 'xlsx']) }}">
                        Template Excel (.xlsx)
                    </x-btn>
                    <x-btn variant="neutral" href="{{ route('master-import.template', ['type' => 'odp', 'format' => 'csv']) }}">
                        Template CSV (.csv)
                    </x-btn>
                </div>
            </div>

            <hr class="my-3">

            @if(auth()->user()->isSuperAdmin())
                <div class="mb-3">
                    <label for="import-odp-pop-id" class="form-label small fw-semibold">
                        Pilih POP Target <span class="text-danger">*</span>
                    </label>
                    <select name="pop_id" id="import-odp-pop-id" class="form-select form-select-sm" required>
                        @foreach($pops as $pop)
                            <option value="{{ $pop->id }}">POP {{ $pop->code }} - {{ $pop->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="mb-3">
                <label for="import-odp-file" class="form-label small fw-semibold">
                    Pilih Berkas Spreadsheet <span class="text-danger">*</span>
                </label>
                <input type="file" name="file" id="import-odp-file" class="form-control form-control-sm" accept=".xlsx,.csv" required>
                <div class="form-text">Format berkas: .xlsx atau .csv (maksimal 5 MB).</div>
            </div>

    <x-slot:footer>
        <x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>
        <x-btn type="submit" variant="primary" id="btn-submit-import-odp">Unggah &amp; Pratinjau</x-btn>
    </x-slot:footer>
</x-offcanvas-form>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = window.initServerTable('#table-odps', {
        ajaxUrl: '{{ route('odps.index') }}',
        filterForm: '#filter-odps-form',
        searchInput: '#filter-odp-search',
        resetButton: '#btn-reset-odp-filter',
        pageLength: 15,
        order: [[0, 'asc']],
        columns: [
            { data: 'name', name: 'name', className: 'fw-semibold text-dark' },
            {
                data: 'odc_name',
                name: 'odc.name',
                render: function (data) {
                    return `<span class="badge bg-light text-dark border">${data || '—'}</span>`;
                }
            },
            { data: 'pop_code', name: 'pop.code', className: 'text-center' },
            {
                data: 'coordinates',
                name: 'coordinates',
                className: 'font-monospace small',
                render: function (data, type, row) {
                    if (row.latitude && row.longitude) {
                        return `${data} <a href="https://www.google.com/maps?q=${row.latitude},${row.longitude}" target="_blank" rel="noopener noreferrer" class="text-primary ms-1"></a>`;
                    }
                    return '<span class="text-muted">—</span>';
                }
            },
            {
                data: 'customers_count',
                name: 'customers_count',
                className: 'text-center',
                render: function (data) {
                    return `<span class="badge bg-light text-dark border">${data} Pelanggan</span>`;
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
                searchable: false,
                className: 'text-end',
                render: function (data, type, row) {
                    const editBtn = window.btnIconHtml('edit', {
                        'class': 'btn-edit-odp',
                        'data-id': row.id,
                        'title': 'Ubah ODP'
                    });
                    let deleteBtn = '';
                    if (row.is_used) {
                        deleteBtn = window.btnIconHtml('delete', {
                            'title': 'Tidak dapat dihapus karena terpakai',
                            'disabled': 'disabled'
                        });
                    } else {
                        deleteBtn = window.btnIconHtml('delete', {
                            'class': 'btn-delete-odp',
                            'data-id': row.id,
                            'data-name': row.name,
                            'data-url': row.urls.delete,
                            'title': 'Hapus ODP'
                        });
                    }

                    return `<div class="btn-group btn-group-sm">${editBtn}${deleteBtn}</div>`;
                }
            }
        ]
    });

    const popSelectEl = document.getElementById('odp-pop-id');
    const odcSelectEl = document.getElementById('odp-odc-id');

    function filterOdcOptionsByPop(popId) {
        if (!odcSelectEl) return;
        const options = odcSelectEl.querySelectorAll('option');
        options.forEach(opt => {
            if (!opt.value) return;
            const optPop = opt.getAttribute('data-pop-id');
            if (!popId || optPop === String(popId)) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
        const currentSelected = odcSelectEl.selectedOptions[0];
        if (currentSelected && currentSelected.style.display === 'none') {
            odcSelectEl.value = '';
        }
    }

    if (popSelectEl && popSelectEl.tagName === 'SELECT') {
        popSelectEl.addEventListener('change', function () {
            filterOdcOptionsByPop(this.value);
        });
    }

    // Filter Card POP -> ODC cascading
    const filterPopEl = document.getElementById('filter-pop');
    const filterOdcEl = document.getElementById('filter-odc');
    if (filterPopEl && filterOdcEl) {
        filterPopEl.addEventListener('change', function () {
            const popId = this.value;
            filterOdcEl.querySelectorAll('option').forEach(opt => {
                if (!opt.value) return;
                const optPop = opt.getAttribute('data-pop-id');
                opt.style.display = (!popId || optPop === String(popId)) ? '' : 'none';
            });
            filterOdcEl.value = '';
        });
    }

    const coordInput = document.getElementById('odp-coordinates');
    const mapBtn = document.getElementById('btn-open-odp-map');
    const getLocBtn = document.getElementById('btn-get-odp-loc');
    const latHidden = document.getElementById('odp-latitude');
    const lngHidden = document.getElementById('odp-longitude');

    function syncCoordinates() {
        if (!coordInput) return;
        const val = coordInput.value.trim();
        if (val && val.includes(',')) {
            const parts = val.split(',').map(s => s.trim());
            if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
                latHidden.value = parts[0];
                lngHidden.value = parts[1];
                if (mapBtn) {
                    mapBtn.href = `https://www.google.com/maps?q=${encodeURIComponent(val)}`;
                    mapBtn.classList.remove('d-none');
                }
                return;
            }
        }
        latHidden.value = '';
        lngHidden.value = '';
        if (mapBtn) mapBtn.classList.add('d-none');
    }

    if (coordInput) {
        coordInput.addEventListener('input', syncCoordinates);
    }

    if (getLocBtn) {
        getLocBtn.addEventListener('click', function () {
            if (!navigator.geolocation) {
                window.toast('error', 'Geolocation tidak didukung browser ini.');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                pos => {
                    coordInput.value = `${pos.coords.latitude.toFixed(7)}, ${pos.coords.longitude.toFixed(7)}`;
                    syncCoordinates();
                    window.toast('success', 'Lokasi berhasil dideteksi.');
                },
                err => {
                    window.toast('error', 'Gagal mendeteksi lokasi: ' + err.message);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }

    // CRUD Offcanvas ODP
    const crudOdp = window.initCrudOffcanvas({
        offcanvasSelector: '#offcanvas-odp',
        storeUrl: '{{ route('odps.store') }}',
        baseUrl: '{{ url('odps') }}',
        createTitle: 'Tambah ODP',
        editTitle: 'Ubah Data ODP',
        table: table,
        onOpenCreate: function (form) {
            document.getElementById('odp-used-warning').classList.add('d-none');
            document.getElementById('odp-is-active').checked = true;
            coordInput.value = '';
            syncCoordinates();
            const popId = popSelectEl ? popSelectEl.value : '';
            filterOdcOptionsByPop(popId);
        },
        onOpenEdit: function (form, record) {
            if (record.is_used) {
                document.getElementById('odp-used-warning').classList.remove('d-none');
            } else {
                document.getElementById('odp-used-warning').classList.add('d-none');
            }
            document.getElementById('odp-is-active').checked = !!record.is_active;
            coordInput.value = record.coordinates && record.coordinates !== '—' ? record.coordinates : '';
            syncCoordinates();
            const popId = record.pop_id || (popSelectEl ? popSelectEl.value : '');
            filterOdcOptionsByPop(popId);
            if (odcSelectEl && record.odc_id) {
                odcSelectEl.value = record.odc_id;
            }
        }
    });

    document.getElementById('btn-open-create-odp')?.addEventListener('click', function () {
        crudOdp.openCreate();
    });

    $(document).on('click', '.btn-edit-odp', function () {
        const id = $(this).data('id');
        crudOdp.openEdit(id, `{{ url('odps') }}/${id}`);
    });

    $(document).on('click', '.btn-delete-odp', async function () {
        const url = $(this).data('url');
        const name = $(this).data('name');

        const confirm = await window.confirmDialog({
            title: 'Hapus ODP?',
            text: `Hapus data ODP "${name}"? Tindakan ini tidak dapat dibatalkan.`,
            confirmButtonText: 'Ya, Hapus',
            confirmButtonClass: 'btn btn-danger',
        });

        if (!confirm || !confirm.isConfirmed) return;

        try {
            const res = await window.apiRequest(url, { method: 'DELETE' });
            window.toast('success', res.message || 'ODP berhasil dihapus.');
            table.ajax.reload(null, false);
        } catch (err) {
            window.toast('warning', err.message || 'Gagal menghapus ODP.');
        }
    });

    // Import Offcanvas Trigger
    document.getElementById('btn-open-import-odp')?.addEventListener('click', function () {
        window.showOffcanvas('#offcanvas-import-odp');
    });

    document.getElementById('form-import-odp')?.addEventListener('submit', function () {
        const btn = document.getElementById('btn-submit-import-odp');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mengunggah berkas...';
    });
});
</script>
@endpush

