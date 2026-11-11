import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import { showToast, escapeHtml } from './toast.js';

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
    if (!dateStr) return '—';
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

/**
 * Inisialisasi DataTable Server-Side
 * @param {string|HTMLElement} tableSelector
 * @param {object} options
 * @returns {DataTable}
 */
export function initServerTable(tableSelector, options = {}) {
    const $table = $(tableSelector);
    if (!$table.length) return null;

    const filterFormSelector = options.filterForm || null;
    const searchInputSelector = options.searchInput || '#filter-search';
    const resetButtonSelector = options.resetButton || '#btn-reset-filter';

    const defaultLanguage = {
        lengthMenu: 'Tampilkan _MENU_ data',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
        infoEmpty: 'Menampilkan 0 data',
        infoFiltered: '(disaring dari _MAX_ total data)',
        zeroRecords: 'Tidak ada data',
        processing: '<div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>Memuat...',
        paginate: {
            first: 'Awal',
            last: 'Akhir',
            next: 'Berikutnya',
            previous: 'Sebelumnya',
        },
    };

    const config = {
        serverSide: true,
        processing: true,
        searching: false, // Matikan input searching bawaan
        pageLength: options.pageLength || 15,
        lengthMenu: [10, 15, 25, 50, 100],
        order: options.order || [[0, 'desc']],
        ajax: {
            url: options.ajaxUrl,
            type: 'GET',
            data: function (d) {
                // Ambil nilai search dari searchInputSelector jika tersedia
                if (searchInputSelector && $(searchInputSelector).length) {
                    d.search = $(searchInputSelector).val() || '';
                }

                // Sambungkan filter eksternal ke protokol DataTables
                if (filterFormSelector && $(filterFormSelector).length) {
                    const formData = new FormData($(filterFormSelector)[0]);
                    for (const [key, val] of formData.entries()) {
                        if (val !== '') {
                            d[key] = val;
                        }
                    }
                }
                // Jika ada custom data injector
                if (typeof options.extraData === 'function') {
                    options.extraData(d);
                }
            },
            error: function () {
                showToast('error', 'Gagal memuat data dari server');
            },
        },
        columns: options.columns || [],
        language: { ...defaultLanguage, ...(options.language || {}) },
        dom: "<'row g-2 align-items-center mb-3'<'col-sm-6'l><'col-sm-6 text-sm-end'>>" +
             "<'table-responsive'tr>" +
             "<'row g-2 align-items-center mt-3'<'col-sm-6'i><'col-sm-6'p>>",
    };

    const table = new DataTable($table[0], config);

    // Tangani error event DataTables
    $table.on('error.dt', function () {
        showToast('error', 'Gagal memuat data dari server');
    });

    // Debounce search input (±400 ms)
    if (searchInputSelector && $(searchInputSelector).length) {
        let debounceTimer;
        $(searchInputSelector).on('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                table.ajax.reload();
            }, 400);
        });
    }

    // Sambungkan change event pada filter input/select lain
    if (filterFormSelector && $(filterFormSelector).length) {
        $(filterFormSelector).find('select, input[type="date"], input[type="month"]').on('change', function () {
            table.ajax.reload();
        });

        // Tombol Reset mengosongkan semua filter lalu reload
        if (resetButtonSelector && $(resetButtonSelector).length) {
            $(resetButtonSelector).on('click', function (e) {
                e.preventDefault();
                const form = $(filterFormSelector)[0];
                if (form) {
                    form.reset();
                    // Reset field yang mungkin tidak kembali ke default
                    $(form).find('input[type="text"], input[type="search"]').val('');
                    $(form).find('select').val('');
                }
                table.ajax.reload();
            });
        }
    }

    return table;
}
