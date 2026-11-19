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

# 1. customer_applications/index.blade.php
update_file('resources/views/customer_applications/index.blade.php', [
    (
        '<button type="button" id="btn-reset-admin-app-filter" class="btn btn-sm btn-outline-secondary w-100" title="Reset">\n                </button>',
        '<x-btn variant="neutral" id="btn-reset-admin-app-filter" class="w-100">Reset</x-btn>'
    ),
    (
        '<a href="#" target="_blank" rel="noopener noreferrer" id="detail-map-link" class="btn btn-outline-secondary d-none">\n                            Peta\n                        </a>',
        '<x-btn variant="neutral" href="#" target="_blank" rel="noopener noreferrer" id="detail-map-link" class="d-none">Peta</x-btn>'
    ),
    (
        '<a href="#" target="_blank" rel="noopener noreferrer" id="detail-ktp-link" class="btn btn-sm btn-outline-primary">\n                                Buka KTP\n                            </a>',
        '<x-btn variant="export" href="#" target="_blank" rel="noopener noreferrer" id="detail-ktp-link">Buka KTP</x-btn>'
    ),
    (
        '<div id="review-actions-group" class="d-flex gap-2">\n                <button type="button" class="btn btn-sm btn-outline-danger" id="btn-reject-app">\n                    Tolak\n                </button>\n                <button type="button" class="btn btn-sm btn-warning text-dark" id="btn-revision-app">\n                    Perbaikan\n                </button>\n                <button type="button" class="btn btn-sm btn-success px-3" id="btn-approve-app">\n                    Setujui\n                </button>\n            </div>',
        '<div id="review-actions-group" class="d-flex gap-2">\n                <x-btn variant="danger" id="btn-reject-app">Tolak</x-btn>\n                <x-btn variant="warning" id="btn-revision-app">Perbaikan</x-btn>\n                <x-btn variant="approve" id="btn-approve-app">Setujui</x-btn>\n            </div>'
    ),
    (
        '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Tutup</button>',
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Tutup</x-btn>'
    ),
    (
        "render: function (data, type, row) {\n                    return `<span class=\"badge bg-${row.status_badge}\">${row.status_label}</span>`;\n                }",
        "render: function (data, type, row) {\n                    return window.statusBadge('application_status', data || row.status);\n                }"
    ),
    (
        "render: function (data, type, row) {\n                    return `\n                        <button type=\"button\" class=\"btn btn-sm btn-outline-primary py-1 px-2 btn-review-app\" data-id=\"${row.id}\">\n                            Periksa\n                        </button>\n                    `;\n                }",
        "render: function (data, type, row) {\n                    return window.btnIconHtml('view', { class: 'btn-review-app', 'data-id': row.id, title: 'Periksa Pendaftaran' });\n                }"
    )
])

# 2. marketing/applications/index.blade.php
update_file('resources/views/marketing/applications/index.blade.php', [
    (
        '<button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" id="btn-add-application">\n            <span>Pendaftaran Baru</span>\n        </button>',
        '<x-btn variant="primary" id="btn-add-application">Pendaftaran Baru</x-btn>'
    ),
    (
        '<button type="button" id="btn-reset-app-filter" class="btn btn-sm btn-outline-secondary w-100">\n                Reset\n            </button>',
        '<x-btn variant="neutral" id="btn-reset-app-filter" class="w-100">Reset</x-btn>'
    ),
    (
        '<button type="button" class="btn btn-outline-secondary" id="btn-get-location" title="Gunakan Lokasi Saya">\n                            Lokasi Saya\n                        </button>',
        '<x-btn variant="neutral" type="button" id="btn-get-location" title="Gunakan Lokasi Saya">Lokasi Saya</x-btn>'
    ),
    (
        '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Tutup</button>\n            <button type="submit" class="btn btn-sm btn-primary px-3" id="btn-submit-application">\n                Kirim Pendaftaran\n            </button>',
        '<x-btn variant="neutral" data-bs-dismiss="offcanvas">Tutup</x-btn>\n            <x-btn type="submit" variant="primary" id="btn-submit-application">Kirim Pendaftaran</x-btn>'
    ),
    (
        "render: function (data, type, row) {\n                    return `<span class=\"badge bg-${row.status_badge}\">${row.status_label}</span>`;\n                }",
        "render: function (data, type, row) {\n                    return window.statusBadge('application_status', data || row.status);\n                }"
    ),
    (
        re.compile(r"render:\s*function\s*\(data,\s*type,\s*row\)\s*\{\s*const\s*btnIcon[^\}]*\}\s*;", re.DOTALL),
        "render: function (data, type, row) {\n                    const action = row.can_edit ? 'edit' : 'view';\n                    return window.btnIconHtml(action, { class: 'btn-edit-app', 'data-id': row.id, title: row.can_edit ? 'Ubah Pendaftaran' : 'Detail Pendaftaran' });\n                };"
    ),
    (
        '<span class="badge bg-secondary font-monospace">{{ $pop?->code }} - {{ $pop?->name }}</span>',
        '<x-status-badge type="role" :status="$pop?->code" /> {{ $pop?->name }}'
    )
])

