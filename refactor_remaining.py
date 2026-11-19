import re

def update_file(path, replacements):
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()
    for pattern, repl in replacements:
        if isinstance(pattern, str):
            content = content.replace(pattern, repl)
        else:
            content = pattern.sub(repl, content)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Updated {path}")

# 1. marketing_fees/index.blade.php
update_file('resources/views/marketing_fees/index.blade.php', [
    (
        '<button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1" id="btn-export-fees">\n            <span>Ekspor XLSX</span>\n        </button>',
        '<x-btn variant="export" id="btn-export-fees">Ekspor XLSX</x-btn>'
    ),
    (
        '<button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 d-none" id="btn-bulk-pay">\n            <span id="bulk-pay-label">Tandai Dibayar</span>\n        </button>',
        '<x-btn variant="approve" class="d-none" id="btn-bulk-pay"><span id="bulk-pay-label">Tandai Dibayar</span></x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+id="btn-reset-fee-filter"[^>]*>.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" id="btn-reset-fee-filter" class="w-100">Reset</x-btn>'
    ),
    (
        '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>\n                    <button type="submit" class="btn btn-sm btn-primary" id="btn-save-amount">Simpan</button>',
        '<x-btn variant="neutral" data-bs-dismiss="modal">Batal</x-btn>\n                    <x-btn type="submit" variant="primary" id="btn-save-amount">Simpan</x-btn>'
    ),
    (
        '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>\n                    <button type="submit" class="btn btn-sm btn-success" id="btn-confirm-pay">',
        '<x-btn variant="neutral" data-bs-dismiss="modal">Batal</x-btn>\n                    <x-btn type="submit" variant="approve" id="btn-confirm-pay">'
    ),
    (
        re.compile(r'render:\s*function\s*\(data\)\s*\{\s*const color = data === \'PAID\'[^\}]*\}', re.DOTALL),
        "render: function (data) { return window.statusBadge('fee_status', data); }"
    ),
    (
        re.compile(r"render:\s*function\s*\(data,\s*type,\s*row\)\s*\{\s*if\s*\(!row\.is_paid\)\s*\{[^\}]*return '<span class=\"text-muted small\">-</span>';\s*\}", re.DOTALL),
        """render: function (data, type, row) {
                    if (!row.is_paid) {
                        return `<button type="button" class="btn btn-sm btn-success py-1 px-2 btn-pay-single" data-id="${row.id}" title="Tandai Dibayar">Bayar</button>`;
                    }
                    if (row.can_unpay) {
                        return `<button type="button" class="btn btn-sm btn-danger py-1 px-2 btn-unpay" data-id="${row.id}" title="Batalkan Pembayaran">Batal</button>`;
                    }
                    return '<span class="text-muted small">-</span>';
                }"""
    )
])

# 2. marketing_profiles/index.blade.php
update_file('resources/views/marketing_profiles/index.blade.php', [
    (
        re.compile(r'<button\s+type="button"\s+id="btn-reset-profile-filter"[^>]*>.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" id="btn-reset-profile-filter" class="w-100">Reset</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+class="btn btn-sm btn-outline-secondary"\s+data-bs-dismiss="offcanvas">Batal</button>\s*<button\s+type="submit"\s+class="btn btn-sm btn-primary[^"]*"\s+id="btn-save-payout">.*?Simpan Data Pembayaran.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>\n            <x-btn type="submit" variant="primary" id="btn-save-payout">Simpan Data Pembayaran</x-btn>'
    ),
    (
        re.compile(r"render:\s*function\s*\(data,\s*type,\s*row\)\s*\{\s*return `\s*<button type=\"button\" class=\"btn btn-sm btn-outline-primary py-1 px-2 btn-edit-payout\" data-id=\"\$\{row\.id\}\">\s*Rekening\s*</button>\s*`;\s*\}", re.DOTALL),
        "render: function (data, type, row) { return window.btnIconHtml('edit', { class: 'btn-edit-payout', 'data-id': row.id, title: 'Ubah Data Rekening' }); }"
    )
])

# 3. dashboard/index.blade.php
update_file('resources/views/dashboard/index.blade.php', [
    (
        '<button type="submit" id="btn-dash-filter" class="btn btn-primary btn-sm px-3">\n                        Terapkan\n                    </button>\n                    <a href="{{ route(\'dashboard\') }}" class="btn btn-outline-secondary btn-sm px-3">\n                        Reset\n                    </a>',
        '<x-btn type="submit" variant="primary" id="btn-dash-filter">Terapkan</x-btn>\n                    <x-btn variant="neutral" href="{{ route(\'dashboard\') }}">Reset</x-btn>'
    ),
    (
        '<span class="badge bg-secondary py-2 px-3 small">\n                            POP {{ auth()->user()->pop?->code }} - {{ auth()->user()->pop?->name }}\n                        </span>',
        '<span class="badge bg-light text-dark border py-2 px-3 small">POP {{ auth()->user()->pop?->code }} - {{ auth()->user()->pop?->name }}</span>'
    ),
    (
        '<span class="badge bg-danger text-white" style="font-size:.7rem;">Tunggakan Terlama</span>',
        '<span class="badge bg-danger" style="font-size:.7rem;">Tunggakan Terlama</span>'
    )
])

# 4. activity_logs/index.blade.php
update_file('resources/views/activity_logs/index.blade.php', [
    (
        '<button type="button" id="btn-reset-log-filter" class="btn btn-sm btn-outline-secondary w-100">\n                Reset\n            </button>',
        '<x-btn variant="neutral" id="btn-reset-log-filter" class="w-100">Reset</x-btn>'
    ),
    (
        "render: function (data) {\n                    return `<span class=\"badge bg-secondary font-monospace\">${window.escapeHtml(data)}</span>`;\n                }",
        "render: function (data) { return `<span class=\"badge bg-light text-dark border font-monospace\">${window.escapeHtml(data)}</span>`; }"
    )
])

# 5. announcements/index.blade.php & announcements/partials/offcanvas.blade.php
update_file('resources/views/announcements/index.blade.php', [
    (
        '<button type="button" class="btn btn-sm btn-primary" id="btn-open-create-announcement">\n            Buat Pengumuman\n        </button>',
        '<x-btn variant="primary" id="btn-open-create-announcement">Buat Pengumuman</x-btn>'
    ),
    (
        '<button type="button" id="btn-reset-ann-filter" class="btn btn-sm btn-outline-secondary w-100">\n                Reset\n            </button>',
        '<x-btn variant="neutral" id="btn-reset-ann-filter" class="w-100">Reset</x-btn>'
    ),
    (
        re.compile(r"render:\s*function\s*\(data\)\s*\{\s*if\s*\(data\)\s*\{\s*return '<span class=\"badge bg-success\">Aktif</span>';\s*\}\s*return '<span class=\"badge bg-secondary\">Nonaktif</span>';\s*\}", re.DOTALL),
        "render: function (data) { return window.statusBadge('active_status', data ? 'ACTIVE' : 'INACTIVE'); }"
    ),
    (
        re.compile(r"render:\s*function\s*\(data,\s*type,\s*row\)\s*\{\s*return `\s*<div class=\"btn-group btn-group-sm\">.*?</div>\s*`;\s*\}", re.DOTALL),
        """render: function (data, type, row) {
                    return `<div class="d-flex justify-content-end gap-1">
                        ${window.btnIconHtml('edit', { class: 'btn-edit-announcement', 'data-id': row.id, 'data-url': row.urls.show, title: 'Ubah Pengumuman' })}
                        ${window.btnIconHtml('delete', { class: 'btn-delete-announcement', 'data-url': row.urls.delete, 'data-title': window.escapeHtml(row.title), title: 'Hapus Pengumuman' })}
                    </div>`;
                }"""
    )
])

update_file('resources/views/announcements/partials/offcanvas.blade.php', [
    (
        '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Batal</button>\n            <button type="submit" class="btn btn-sm btn-primary px-3" id="btn-submit-announcement">\n                Simpan\n            </button>',
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>\n            <x-btn type="submit" variant="primary" id="btn-submit-announcement">Simpan</x-btn>'
    )
])

# 6. imports/index.blade.php, imports/preview.blade.php, imports/show.blade.php, master_import/preview.blade.php
update_file('resources/views/imports/index.blade.php', [
    (
        '<button type="submit" class="btn btn-primary btn-sm px-4">\n                                Unggah &amp; Validasi Berkas\n                            </button>',
        '<x-btn type="submit" variant="primary">Unggah &amp; Validasi Berkas</x-btn>'
    ),
    (
        '<a href="{{ route(\'imports.template\', [\'format\' => \'xlsx\']) }}" class="btn btn-outline-secondary btn-sm">\n                                    Unduh Template XLSX\n                                </a>',
        '<x-btn variant="export" href="{{ route(\'imports.template\', [\'format\' => \'xlsx\']) }}">Unduh Template XLSX</x-btn>'
    ),
    (
        '<a href="{{ route(\'imports.template\', [\'format\' => \'csv\']) }}" class="btn btn-outline-secondary btn-sm">\n                                    Unduh Template CSV\n                                </a>',
        '<x-btn variant="neutral" href="{{ route(\'imports.template\', [\'format\' => \'csv\']) }}">Unduh Template CSV</x-btn>'
    ),
    (
        '<span class="badge bg-secondary font-monospace">Belum Pernah</span>',
        '<x-status-badge type="active_status" status="INACTIVE" />'
    ),
    (
        '<span class="badge bg-success">Berhasil Selesai</span>',
        '<x-status-badge type="active_status" status="ACTIVE" />'
    ),
    (
        '<span class="badge bg-primary">Sedang Berlangsung</span>',
        '<x-status-badge type="work_order_status" status="IN_PROGRESS" />'
    ),
    (
        re.compile(r'<a\s+href="[^"]*imports\.show[^"]*" class="btn btn-outline-secondary btn-sm py-0">', re.DOTALL),
        '<a href="{{ route(\'imports.show\', $lastBatch) }}" class="btn btn-sm btn-outline-secondary py-0">'
    )
])

update_file('resources/views/imports/preview.blade.php', [
    (
        '<button type="submit" class="btn btn-primary btn-sm px-4" id="btn-confirm-import">\n                            Proses Import Sekarang\n                        </button>\n                        <a href="{{ route(\'imports.index\') }}" class="btn btn-outline-secondary btn-sm">\n                            Batal\n                        </a>',
        '<x-btn type="submit" variant="primary" id="btn-confirm-import">Proses Import Sekarang</x-btn>\n                        <x-btn variant="neutral" href="{{ route(\'imports.index\') }}">Batal</x-btn>'
    )
])

update_file('resources/views/imports/show.blade.php', [
    (
        '<a href="{{ route(\'imports.index\') }}" class="btn btn-outline-secondary btn-sm">\n            Kembali ke Halaman Import\n        </a>',
        '<x-btn variant="neutral" href="{{ route(\'imports.index\') }}">Kembali ke Halaman Import</x-btn>'
    )
])

update_file('resources/views/master_import/preview.blade.php', [
    (
        '<button type="submit" class="btn btn-primary btn-sm px-4">\n                        Mulai Eksekusi Import Sekarang\n                    </button>\n                    <a href="{{ $entityConfig[\'index_route\'] }}" class="btn btn-outline-secondary btn-sm">\n                        Batal &amp; Kembali\n                    </a>',
        '<x-btn type="submit" variant="primary">Mulai Eksekusi Import Sekarang</x-btn>\n                    <x-btn variant="neutral" href="{{ $entityConfig[\'index_route\'] }}">Batal &amp; Kembali</x-btn>'
    )
])

# 7. settings/fee.blade.php, settings/noc.blade.php, portal/index.blade.php, auth/login.blade.php, auth/set-password.blade.php
update_file('resources/views/settings/fee.blade.php', [
    (
        '<button type="submit" class="btn btn-primary btn-sm px-4" id="btn-save-fee-settings">\n                Simpan Pengaturan\n            </button>',
        '<x-btn type="submit" variant="primary" id="btn-save-fee-settings">Simpan Pengaturan</x-btn>'
    )
])

update_file('resources/views/settings/noc.blade.php', [
    (
        '<button type="submit" class="btn btn-primary btn-sm px-4" id="btn-save-noc-settings">\n                Simpan Pengaturan\n            </button>',
        '<x-btn type="submit" variant="primary" id="btn-save-noc-settings">Simpan Pengaturan</x-btn>'
    )
])

update_file('resources/views/portal/index.blade.php', [
    (
        '<button type="submit" class="btn btn-primary btn-sm">\n                            Cari Tagihan\n                        </button>',
        '<x-btn type="submit" variant="primary">Cari Tagihan</x-btn>'
    )
])

update_file('resources/views/auth/login.blade.php', [
    (
        '<button type="submit" class="btn btn-primary w-100 py-2">\n                            Masuk ke NetBill\n                        </button>',
        '<x-btn type="submit" variant="primary" class="w-100">Masuk ke NetBill</x-btn>'
    )
])

update_file('resources/views/auth/set-password.blade.php', [
    (
        '<button type="submit" class="btn btn-primary w-100 py-2">\n                            Simpan Kata Sandi\n                        </button>',
        '<x-btn type="submit" variant="primary" class="w-100">Simpan Kata Sandi</x-btn>'
    )
])

# 8. components/task-card.blade.php & layouts/partials/topbar.blade.php
update_file('resources/views/components/task-card.blade.php', [
    (
        '<span class="badge bg-{{ $badgeColor }}">{{ $badge }}</span>',
        '<x-status-badge :type="null" :status="$badge" />'
    )
])

