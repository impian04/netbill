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

# 1. materials/index.blade.php
update_file('resources/views/materials/index.blade.php', [
    (
        '<button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1" id="btn-open-import-material">\n            <span>Import Barang</span>\n        </button>',
        '<x-btn variant="export" id="btn-open-import-material">Import Barang</x-btn>'
    ),
    (
        '<button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" id="btn-open-create-material">\n            <span>Tambah Barang</span>\n        </button>',
        '<x-btn variant="primary" id="btn-open-create-material">Tambah Barang</x-btn>'
    ),
    (
        '<button type="button" id="btn-reset-material-filter" class="btn btn-sm btn-outline-secondary w-100" title="Reset">\n                </button>',
        '<x-btn variant="neutral" id="btn-reset-material-filter" class="w-100">Reset</x-btn>'
    ),
    (
        '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Batal</button>\n            <button type="submit" class="btn btn-sm btn-primary px-3" id="btn-save-material">\n                Simpan Barang\n            </button>',
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>\n            <x-btn type="submit" variant="primary" id="btn-save-material">Simpan Barang</x-btn>'
    ),
    (
        '<a href="{{ route(\'master-import.template\', [\'type\' => \'material\', \'format\' => \'xlsx\']) }}" class="btn btn-sm btn-outline-success">\n                        Template Excel (.xlsx)\n                    </a>\n                    <a href="{{ route(\'master-import.template\', [\'type\' => \'material\', \'format\' => \'csv\']) }}" class="btn btn-sm btn-outline-secondary">\n                        Template CSV (.csv)\n                    </a>',
        '<x-btn variant="export" href="{{ route(\'master-import.template\', [\'type\' => \'material\', \'format\' => \'xlsx\']) }}">Template Excel (.xlsx)</x-btn>\n                    <x-btn variant="neutral" href="{{ route(\'master-import.template\', [\'type\' => \'material\', \'format\' => \'csv\']) }}">Template CSV (.csv)</x-btn>'
    ),
    (
        '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Batal</button>\n            <button type="submit" class="btn btn-sm btn-primary px-3" id="btn-submit-import-material">\n                Unggah &amp; Pratinjau\n            </button>',
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>\n            <x-btn type="submit" variant="primary" id="btn-submit-import-material">Unggah &amp; Pratinjau</x-btn>'
    ),
    (
        re.compile(r"render:\s*function\s*\(data\)\s*\{\s*return\s*data\s*\?[^\}]*;\s*\}", re.DOTALL),
        "render: function (data) { return window.statusBadge('active_status', data ? 'ACTIVE' : 'INACTIVE'); }"
    ),
    (
        re.compile(r"render:\s*function\s*\(data,\s*type,\s*row\)\s*\{\s*let deleteBtn[^\}]*return `\s*<div class=\"btn-group btn-group-sm\">.*?</div>\s*`;\s*\}", re.DOTALL),
        """render: function (data, type, row) {
                    let deleteBtn = row.is_used ? '' : window.btnIconHtml('delete', { class: 'btn-delete-material', 'data-id': row.id, 'data-name': row.name, 'data-url': row.urls.delete, title: 'Hapus Barang' });
                    return `<div class="d-flex justify-content-end gap-1">
                        ${window.btnIconHtml('edit', { class: 'btn-edit-material', 'data-id': row.id, title: 'Ubah Barang' })}
                        ${deleteBtn}
                    </div>`;
                }"""
    )
])

# 2. odcs/index.blade.php
update_file('resources/views/odcs/index.blade.php', [
    (
        re.compile(r'<button\s+type="button"\s+class="btn btn-sm btn-outline-success[^"]*"\s+id="btn-open-import-odc">.*?<span>Import ODC</span>.*?</button>', re.DOTALL),
        '<x-btn variant="export" id="btn-open-import-odc">Import ODC</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+class="btn btn-sm btn-primary[^"]*"\s+id="btn-open-create-odc">.*?<span>Tambah ODC</span>.*?</button>', re.DOTALL),
        '<x-btn variant="primary" id="btn-open-create-odc">Tambah ODC</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+id="btn-reset-odc-filter"[^>]*>.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" id="btn-reset-odc-filter" class="w-100">Reset</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+class="btn btn-sm btn-outline-secondary"\s+data-bs-dismiss="offcanvas">Batal</button>\s*<button\s+type="submit"\s+class="btn btn-sm btn-primary[^"]*"\s+id="btn-save-odc">.*?Simpan ODC.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>\n            <x-btn type="submit" variant="primary" id="btn-save-odc">Simpan ODC</x-btn>'
    ),
    (
        re.compile(r'<a\s+href="[^"]*format=xlsx"[^>]*>.*?Template Excel.*?</a>\s*<a\s+href="[^"]*format=csv"[^>]*>.*?Template CSV.*?</a>', re.DOTALL),
        '<x-btn variant="export" href="{{ route(\'master-import.template\', [\'type\' => \'odc\', \'format\' => \'xlsx\']) }}">Template Excel (.xlsx)</x-btn>\n                    <x-btn variant="neutral" href="{{ route(\'master-import.template\', [\'type\' => \'odc\', \'format\' => \'csv\']) }}">Template CSV (.csv)</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+class="btn btn-sm btn-outline-secondary"\s+data-bs-dismiss="offcanvas">Batal</button>\s*<button\s+type="submit"\s+class="btn btn-sm btn-primary[^"]*"\s+id="btn-submit-import-odc">.*?Unggah &amp; Pratinjau.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>\n            <x-btn type="submit" variant="primary" id="btn-submit-import-odc">Unggah &amp; Pratinjau</x-btn>'
    ),
    (
        re.compile(r"render:\s*function\s*\(data\)\s*\{\s*return\s*data\s*\?[^\}]*;\s*\}", re.DOTALL),
        "render: function (data) { return window.statusBadge('active_status', data ? 'ACTIVE' : 'INACTIVE'); }"
    ),
    (
        re.compile(r"render:\s*function\s*\(data,\s*type,\s*row\)\s*\{\s*let deleteBtn[^\}]*return `\s*<div class=\"btn-group btn-group-sm\">.*?</div>\s*`;\s*\}", re.DOTALL),
        """render: function (data, type, row) {
                    let deleteBtn = row.odp_count > 0 ? '' : window.btnIconHtml('delete', { class: 'btn-delete-odc', 'data-id': row.id, 'data-name': row.name, 'data-url': row.urls.delete, title: 'Hapus ODC' });
                    return `<div class="d-flex justify-content-end gap-1">
                        ${window.btnIconHtml('edit', { class: 'btn-edit-odc', 'data-id': row.id, title: 'Ubah ODC' })}
                        ${deleteBtn}
                    </div>`;
                }"""
    )
])

# 3. odps/index.blade.php
update_file('resources/views/odps/index.blade.php', [
    (
        re.compile(r'<button\s+type="button"\s+class="btn btn-sm btn-outline-success[^"]*"\s+id="btn-open-import-odp">.*?<span>Import ODP</span>.*?</button>', re.DOTALL),
        '<x-btn variant="export" id="btn-open-import-odp">Import ODP</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+class="btn btn-sm btn-primary[^"]*"\s+id="btn-open-create-odp">.*?<span>Tambah ODP</span>.*?</button>', re.DOTALL),
        '<x-btn variant="primary" id="btn-open-create-odp">Tambah ODP</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+id="btn-reset-odp-filter"[^>]*>.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" id="btn-reset-odp-filter" class="w-100">Reset</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+class="btn btn-sm btn-outline-secondary"\s+data-bs-dismiss="offcanvas">Batal</button>\s*<button\s+type="submit"\s+class="btn btn-sm btn-primary[^"]*"\s+id="btn-save-odp">.*?Simpan ODP.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>\n            <x-btn type="submit" variant="primary" id="btn-save-odp">Simpan ODP</x-btn>'
    ),
    (
        re.compile(r'<a\s+href="[^"]*format=xlsx"[^>]*>.*?Template Excel.*?</a>\s*<a\s+href="[^"]*format=csv"[^>]*>.*?Template CSV.*?</a>', re.DOTALL),
        '<x-btn variant="export" href="{{ route(\'master-import.template\', [\'type\' => \'odp\', \'format\' => \'xlsx\']) }}">Template Excel (.xlsx)</x-btn>\n                    <x-btn variant="neutral" href="{{ route(\'master-import.template\', [\'type\' => \'odp\', \'format\' => \'csv\']) }}">Template CSV (.csv)</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+class="btn btn-sm btn-outline-secondary"\s+data-bs-dismiss="offcanvas">Batal</button>\s*<button\s+type="submit"\s+class="btn btn-sm btn-primary[^"]*"\s+id="btn-submit-import-odp">.*?Unggah &amp; Pratinjau.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>\n            <x-btn type="submit" variant="primary" id="btn-submit-import-odp">Unggah &amp; Pratinjau</x-btn>'
    ),
    (
        re.compile(r"render:\s*function\s*\(data\)\s*\{\s*return\s*data\s*\?[^\}]*;\s*\}", re.DOTALL),
        "render: function (data) { return window.statusBadge('active_status', data ? 'ACTIVE' : 'INACTIVE'); }"
    ),
    (
        re.compile(r"render:\s*function\s*\(data,\s*type,\s*row\)\s*\{\s*let deleteBtn[^\}]*return `\s*<div class=\"btn-group btn-group-sm\">.*?</div>\s*`;\s*\}", re.DOTALL),
        """render: function (data, type, row) {
                    let deleteBtn = row.customer_count > 0 ? '' : window.btnIconHtml('delete', { class: 'btn-delete-odp', 'data-id': row.id, 'data-name': row.name, 'data-url': row.urls.delete, title: 'Hapus ODP' });
                    return `<div class="d-flex justify-content-end gap-1">
                        ${window.btnIconHtml('edit', { class: 'btn-edit-odp', 'data-id': row.id, title: 'Ubah ODP' })}
                        ${deleteBtn}
                    </div>`;
                }"""
    )
])

# 4. pops/index.blade.php & pops/partials/offcanvas.blade.php
update_file('resources/views/pops/index.blade.php', [
    (
        '<button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" id="btn-open-create-pop">\n            <span>Tambah POP</span>\n        </button>',
        '<x-btn variant="primary" id="btn-open-create-pop">Tambah POP</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+id="btn-reset-pop-filter"[^>]*>.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" id="btn-reset-pop-filter" class="w-100">Reset</x-btn>'
    ),
    (
        re.compile(r"render:\s*function\s*\(data\)\s*\{\s*return\s*data\s*\?[^\}]*;\s*\}", re.DOTALL),
        "render: function (data) { return window.statusBadge('active_status', data ? 'ACTIVE' : 'INACTIVE'); }"
    ),
    (
        re.compile(r"render:\s*function\s*\(data,\s*type,\s*row\)\s*\{\s*let deleteBtn[^\}]*return `\s*<div class=\"btn-group btn-group-sm\">.*?</div>\s*`;\s*\}", re.DOTALL),
        """render: function (data, type, row) {
                    let deleteBtn = row.has_relations ? '' : window.btnIconHtml('delete', { class: 'btn-delete-pop', 'data-id': row.id, 'data-code': row.code, 'data-url': row.urls.delete, title: 'Hapus POP' });
                    return `<div class="d-flex justify-content-end gap-1">
                        ${window.btnIconHtml('edit', { class: 'btn-edit-pop', 'data-id': row.id, title: 'Ubah POP' })}
                        ${deleteBtn}
                    </div>`;
                }"""
    )
])

update_file('resources/views/pops/partials/offcanvas.blade.php', [
    (
        '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Batal</button>\n        <button type="submit" class="btn btn-sm btn-primary px-3" id="btn-save-pop">\n            Simpan POP\n        </button>',
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>\n        <x-btn type="submit" variant="primary" id="btn-save-pop">Simpan POP</x-btn>'
    )
])

# 5. staff/index.blade.php & staff/partials/offcanvas.blade.php
update_file('resources/views/staff/index.blade.php', [
    (
        '<button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" id="btn-open-create-staff">\n            <span>Tambah Staf</span>\n        </button>',
        '<x-btn variant="primary" id="btn-open-create-staff">Tambah Staf</x-btn>'
    ),
    (
        re.compile(r'<button\s+type="button"\s+id="btn-reset-staff-filter"[^>]*>.*?</button>', re.DOTALL),
        '<x-btn variant="neutral" id="btn-reset-staff-filter" class="w-100">Reset</x-btn>'
    ),
    (
        re.compile(r"render:\s*function\s*\(data\)\s*\{\s*return\s*data\s*\?[^\}]*;\s*\}", re.DOTALL),
        "render: function (data) { return window.statusBadge('active_status', data ? 'ACTIVE' : 'INACTIVE'); }"
    ),
    (
        re.compile(r"render:\s*function\s*\(data,\s*type,\s*row\)\s*\{\s*return `\s*<div class=\"btn-group btn-group-sm\">.*?</div>\s*`;\s*\}", re.DOTALL),
        """render: function (data, type, row) {
                    return `<div class="d-flex justify-content-end gap-1">
                        ${window.btnIconHtml('edit', { class: 'btn-edit-staff', 'data-id': row.id, title: 'Ubah Staf' })}
                    </div>`;
                }"""
    )
])

update_file('resources/views/staff/partials/offcanvas.blade.php', [
    (
        '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Batal</button>\n        <button type="submit" class="btn btn-sm btn-primary px-3" id="btn-save-staff">\n            Simpan Staf\n        </button>',
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Batal</x-btn>\n        <x-btn type="submit" variant="primary" id="btn-save-staff">Simpan Staf</x-btn>'
    )
])

