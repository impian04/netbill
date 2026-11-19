import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import { toast, showToast, escapeHtml } from './toast.js';
import {
    hydrateFormFromUrl,
    initFilterState,
    updateUrlQuery,
    getActiveFilterData,
    normalizeParamName,
} from './filter-state.js';

// Hubungkan DataTable dengan jQuery & matikan alert error bawaan DataTables
if (typeof DataTable.use === 'function') {
    DataTable.use($);
}
if (DataTable.ext) {
    DataTable.ext.errMode = 'none';
} else if ($.fn?.dataTable?.ext) {
    $.fn.dataTable.ext.errMode = 'none';
}

/**
 * Format mata uang Rupiah Indonesia (Intl id-ID)
 */
export function formatRupiah(amount) {
    if (amount === null || amount === undefined || isNaN(amount)) return 'Rp0';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

/**
 * Format tanggal Indonesia: "26 Desember 2025"
 */
export function formatTanggalIndo(dateStr) {
    if (!dateStr) return '–';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return escapeHtml(dateStr);
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(d);
    } catch {
        return escapeHtml(dateStr);
    }
}

export const defaultLanguage = {
    lengthMenu: 'Tampilkan _MENU_ data',
    info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
    infoEmpty: 'Menampilkan 0 data',
    infoFiltered: '(disaring dari _MAX_ total data)',
    zeroRecords: 'Tidak ada data',
    search: 'Cari:',
    processing: '<div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>Memuat...',
    paginate: {
        first: 'Awal',
        last: 'Akhir',
        next: 'Berikutnya',
        previous: 'Sebelumnya',
    },
};

/**
 * Inisialisasi DataTable Server-Side
 *
 * @param {string|HTMLElement} tableSelector
 * @param {object} options
 *   - ajaxUrl {string}            URL endpoint server-side
 *   - filterForm {string}         CSS selector ke elemen <form> filter.
 *   - searchInput {string}        CSS selector ke input pencarian (default '#filter-search').
 *   - resetButton {string}        CSS selector tombol reset (default '#btn-reset-filter').
 *   - isRateLimited {boolean}     Apakah pencarian dibatasi laju (submit Enter/tombol manual)
 *   - extraData {function}        Fungsi (d) => void untuk menyuntikkan parameter tambahan.
 *   - columns, order, pageLength, language — diteruskan ke DataTables.
 * @returns {DataTable|null}
 */
export function initServerTable(tableSelector, options = {}) {
    const $table = $(tableSelector);
    if (!$table.length) return null;

    const filterFormSelector  = options.filterForm  || null;
    const searchInputSelector = options.searchInput || '#filter-search';
    const resetButtonSelector = options.resetButton || '#btn-reset-filter';

    // Validate filterForm: must be an HTMLFormElement, otherwise skip & warn.
    let resolvedFormEl = null;
    if (filterFormSelector) {
        const el = $(filterFormSelector)[0];
        if (!el) {
            console.error(
                `[initServerTable] filterForm selector "${filterFormSelector}" not found` +
                ` (table: ${tableSelector}). Filter form will be ignored.`
            );
        } else if (!(el instanceof HTMLFormElement)) {
            console.error(
                `[initServerTable] filterForm selector "${filterFormSelector}" resolved to` +
                ` a <${el.tagName.toLowerCase()}> element, not a <form>` +
                ` (table: ${tableSelector}). Wrap filter controls in a <form> element.` +
                ` Filter form will be ignored.`
            );
        } else {
            resolvedFormEl = el;
            // Hydrate kontrol form dari parameter URL sebelum request pertama DataTables
            hydrateFormFromUrl(resolvedFormEl);
        }
    }

    let activeXhr = null;

    const config = {
        serverSide: true,
        processing: true,
        searching: false, // Matikan input searching bawaan DataTables
        stateSave: false, // Matikan stateSave agar prioritas selalu URL lalu bawaan
        pageLength: options.pageLength || 15,
        lengthMenu: [10, 15, 25, 50, 100],
        order: options.order || [[0, 'desc']],
        ajax: function (data, callback, settings) {
            // Batalkan request yang masih berjalan sebelum mengirim request baru
            if (activeXhr && activeXhr.readyState !== 4) {
                try {
                    activeXhr.abort();
                } catch (e) {}
            }

            // Ambil nilai search dari searchInputSelector jika tersedia di luar form
            if (searchInputSelector && $(searchInputSelector).length) {
                const sVal = $(searchInputSelector).val() || '';
                data.search = sVal;
                data.q = sVal;
            }

            // Sambungkan filter eksternal dari form yang telah di-hydrate
            if (resolvedFormEl) {
                const formData = new FormData(resolvedFormEl);
                for (const [key, val] of formData.entries()) {
                    if (val !== '') {
                        data[key] = val;
                        // Alias q untuk search
                        if (key === 'search' || key === 'q') {
                            data.q = val;
                            data.search = val;
                        }
                    }
                }
            }

            // Custom data injector
            if (typeof options.extraData === 'function') {
                options.extraData(data);
            }

            activeXhr = $.ajax({
                url: options.ajaxUrl,
                type: 'GET',
                data: data,
                success: function (res) {
                    activeXhr = null;
                    callback(res);
                },
                error: function (xhr, error, thrown) {
                    if (error === 'abort') {
                        // Request dibatalkan dengan sengaja oleh filter baru, abaikan
                        return;
                    }
                    activeXhr = null;
                    showToast('error', 'Gagal memuat data dari server');
                },
            });
        },
        columns: options.columns || [],
        language: { ...defaultLanguage, ...(options.language || {}) },
        dom: "<'row g-2 align-items-center mb-3'<'col-sm-6'l><'col-sm-6 text-sm-end'>>" +
             "<'table-responsive'tr>" +
             "<'row g-2 align-items-center mt-3'<'col-sm-6'i><'col-sm-6'p>>",
    };

    const table = new DataTable($table[0], config);

    // Tangani error event DataTables
    $table.on('error.dt', function (e, settings, techNote, message) {
        showToast('error', 'Gagal memuat data dari server');
    });

    // Inisialisasi pengelola state filter interaktif
    if (resolvedFormEl) {
        initFilterState({
            formSelector: resolvedFormEl,
            isRateLimited: !!options.isRateLimited,
            onFilterChange: function (filterData) {
                table.page('first').ajax.reload();
            },
            onReset: function () {
                table.page('first').ajax.reload();
            },
        });
    }

    return table;
}

/**
 * Inisialisasi DataTable Client-Side untuk tabel data statis
 * @param {string|HTMLElement} tableSelector
 * @param {object} options
 * @returns {DataTable}
 */
export function initClientTable(tableSelector, options = {}) {
    const $table = $(tableSelector);
    if (!$table.length) return null;

    const config = {
        serverSide: false,
        processing: false,
        searching: options.searching ?? false,
        pageLength: options.pageLength || 10,
        lengthMenu: options.lengthMenu || [10, 25, 50],
        order: options.order || [],
        language: { ...defaultLanguage, ...(options.language || {}) },
        dom: options.dom || "<'row g-2 align-items-center mb-3'<'col-sm-6'l><'col-sm-6 text-sm-end'>>" +
             "<'table-responsive'tr>" +
             "<'row g-2 align-items-center mt-3'<'col-sm-6'i><'col-sm-6'p>>",
    };

    return new DataTable($table[0], config);
}

/**
 * Render HTML badge status seragam berdasarkan config/ui.php
 * @param {string} type Kategori status (service_status, ticket_status, dsb)
 * @param {string|number|boolean} status Nilai status
 * @returns {string} HTML span badge
 */
export function statusBadge(type, status) {
    const config = window.UI_CONFIG?.badges || {};
    let item = null;
    const statusKey = String(status ?? '');

    if (type && config[type]) {
        item = config[type][statusKey];
    }
    if (!item) {
        for (const cat in config) {
            if (config[cat] && config[cat][statusKey]) {
                item = config[cat][statusKey];
            }
        }
    }

    const label = item ? item.label : escapeHtml(statusKey || '-');
    const color = item ? item.color : 'secondary';
    return `<span class="badge bg-${color}">${label}</span>`;
}

/**
 * Render HTML tombol aksi baris seragam (<x-btn-icon>)
 * @param {'view'|'edit'|'delete'|'route'|'call'|'copy'|'download'} action
 * @param {object} attrs Atribut tambahan seperti { 'data-id': 12, class: 'btn-edit' }
 * @returns {string} HTML button/a element
 */
export function btnIconHtml(action, attrs = {}) {
    const actionMap = {
        view:     { class: 'btn-outline-secondary', icon: 'bi-eye',       defaultTitle: 'Lihat Detail' },
        edit:     { class: 'btn-outline-primary',   icon: 'bi-pencil',    defaultTitle: 'Ubah' },
        delete:   { class: 'btn-outline-danger',    icon: 'bi-trash',     defaultTitle: 'Hapus' },
        route:    { class: 'btn-outline-secondary', icon: 'bi-geo-alt',   defaultTitle: 'Rute' },
        call:     { class: 'btn-outline-secondary', icon: 'bi-telephone', defaultTitle: 'Telepon' },
        copy:     { class: 'btn-outline-secondary', icon: 'bi-clipboard', defaultTitle: 'Salin' },
        download: { class: 'btn-outline-primary',   icon: 'bi-download',  defaultTitle: 'Unduh' },
    };

    const cfg = actionMap[action] || actionMap.view;
    const title = attrs.title || cfg.defaultTitle;
    const extraClass = attrs.class ? ` ${attrs.class}` : '';
    const href = attrs.href;

    let attrStr = '';
    for (const k in attrs) {
        if (k !== 'class' && k !== 'title' && k !== 'href') {
            attrStr += ` ${k}="${escapeHtml(String(attrs[k]))}"`;
        }
    }

    if (href) {
        return `<a href="${href}" class="btn btn-sm ${cfg.class}${extraClass}" title="${escapeHtml(title)}" aria-label="${escapeHtml(title)}"${attrStr}><i class="bi ${cfg.icon}"></i></a>`;
    }

    return `<button type="button" class="btn btn-sm ${cfg.class}${extraClass}" title="${escapeHtml(title)}" aria-label="${escapeHtml(title)}"${attrStr}><i class="bi ${cfg.icon}"></i></button>`;
}

/**
 * Render HTML tombol seragam dengan teks (<x-btn>) untuk JS DataTables
 * @param {'primary'|'approve'|'warning'|'danger'|'neutral'|'export'} variant
 * @param {string} text Label teks tombol
 * @param {object} attrs Atribut tambahan
 * @returns {string} HTML button/a element
 */
export function btnHtml(variant, text, attrs = {}) {
    const variantMap = {
        primary: 'btn-primary',
        approve: 'btn-success',
        warning: 'btn-warning',
        danger:  'btn-danger',
        neutral: 'btn-outline-secondary',
        export:  'btn-outline-primary',
    };
    const btnClass = variantMap[variant] || 'btn-primary';
    const extraClass = attrs.class ? ` ${attrs.class}` : '';
    const href = attrs.href;

    let attrStr = '';
    for (const k in attrs) {
        if (k !== 'class' && k !== 'href' && k !== 'type') {
            attrStr += ` ${k}="${escapeHtml(String(attrs[k]))}"`;
        }
    }

    if (href) {
        return `<a href="${href}" class="btn btn-sm ${btnClass}${extraClass}"${attrStr}>${escapeHtml(text)}</a>`;
    }

    const type = attrs.type || 'button';
    return `<button type="${type}" class="btn btn-sm ${btnClass}${extraClass}"${attrStr}>${escapeHtml(text)}</button>`;
}
