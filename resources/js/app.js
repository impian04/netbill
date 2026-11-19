import * as bootstrap from 'bootstrap';
import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import Chart from 'chart.js/auto';

import {
    Swal,
    Toast,
    toast,
    showToast,
    confirmDialog,
    confirmAction,
    promptReason,
    showActivationCodeModal,
    showPasswordLinkModal,
    initFlashToasts,
    escapeHtml,
    getActiveOffcanvas,
} from './ui/toast.js';

import {
    initServerTable,
    initClientTable,
    formatRupiah,
    formatTanggalIndo,
    defaultLanguage,
    statusBadge,
    btnIconHtml,
    btnHtml,
} from './ui/datatable.js';

import {
    initCrudOffcanvas,
    showModal,
    hideModal,
    showOffcanvas,
    hideOffcanvas,
    apiRequest,
} from './ui/crud-offcanvas.js';

import {
    initFilterState,
    initCardFilterPartial,
    hydrateFormFromUrl,
    updateUrlQuery,
    getActiveFilterData,
    getUrlParams,
} from './ui/filter-state.js';

// Hubungkan DataTable dengan jQuery
if (typeof DataTable.use === 'function') {
    DataTable.use($);
}

// Global exports untuk digunakan pada Blade script views
window.bootstrap = bootstrap;
window.$ = window.jQuery = $;
window.DataTable = DataTable;
window.Chart = Chart;
window.indonesianDataTablesLanguage = defaultLanguage;

// Toast & Dialog helpers
window.Swal = Swal;
window.Toast = Toast;
window.toast = toast;
window.showToast = showToast;
window.confirmDialog = confirmDialog;
window.confirmAction = confirmAction;
window.promptReason = promptReason;
window.showActivationCodeModal = showActivationCodeModal;
window.showPasswordLinkModal = showPasswordLinkModal;
window.escapeHtml = escapeHtml;
window.getActiveOffcanvas = getActiveOffcanvas;

// UI & Crud Offcanvas helpers
window.initServerTable = initServerTable;
window.initClientTable = initClientTable;
window.formatRupiah = formatRupiah;
window.formatTanggalIndo = formatTanggalIndo;
window.initCrudOffcanvas = initCrudOffcanvas;
window.showModal = showModal;
window.hideModal = hideModal;
window.showOffcanvas = showOffcanvas;
window.hideOffcanvas = hideOffcanvas;
// Alias dipakai di halaman surveyor & technician (Fase 17g)
window.openOffcanvas  = (id) => showOffcanvas(typeof id === 'string' && !id.startsWith('#') ? '#' + id : id);
window.closeOffcanvas = (id) => hideOffcanvas(typeof id === 'string' && !id.startsWith('#') ? '#' + id : id);
window.apiRequest = apiRequest;
window.statusBadge = statusBadge;
window.btnIconHtml = btnIconHtml;
window.btnHtml = btnHtml;

// Filter State helpers (Fase 17k)
window.initFilterState = initFilterState;
window.initCardFilterPartial = initCardFilterPartial;
window.hydrateFormFromUrl = hydrateFormFromUrl;
window.updateUrlQuery = updateUrlQuery;
window.getActiveFilterData = getActiveFilterData;
window.getUrlParams = getUrlParams;

// Helper penanganan gambar gagal muat (fallback placeholder)
window.handleImageError = function (img) {
    if (!img || !img.parentElement) return;
    img.onerror = null;
    img.parentElement.innerHTML = '<div class="d-flex flex-column align-items-center justify-content-center bg-light text-muted small text-center p-2 box-empty-120"><i class="bi bi-image text-secondary fs-4 mb-1"></i><span>Foto tidak tersedia</span></div>';
};

// Inisialisasi flash session toasts otomatis pada setiap halaman
document.addEventListener('DOMContentLoaded', () => {
    initFlashToasts();
});
