import 'bootstrap';
import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import Chart from 'chart.js/auto';

import { Toast, showToast, confirmAction, showActivationCodeModal, initFlashToasts, escapeHtml } from './ui/toast.js';
import { initServerTable, formatRupiah, formatTanggalIndo } from './ui/datatable.js';
import { initCrudOffcanvas } from './ui/crud-offcanvas.js';

// Hubungkan DataTable dengan jQuery
if (typeof DataTable.use === 'function') {
    DataTable.use($);
}

// Global exports untuk digunakan pada Blade script views
window.$ = window.jQuery = $;
window.DataTable = DataTable;
window.Chart = Chart;

window.Toast = Toast;
window.showToast = showToast;
window.confirmAction = confirmAction;
window.showActivationCodeModal = showActivationCodeModal;
window.escapeHtml = escapeHtml;

window.initServerTable = initServerTable;
window.formatRupiah = formatRupiah;
window.formatTanggalIndo = formatTanggalIndo;

window.initCrudOffcanvas = initCrudOffcanvas;

// Inisialisasi flash session toasts otomatis pada setiap halaman
document.addEventListener('DOMContentLoaded', () => {
    initFlashToasts();
});
