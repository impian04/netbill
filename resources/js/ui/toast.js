import Swal from 'sweetalert2';

/**
 * Toast global SweetAlert2:
 * Swal.mixin({toast:true, position:'top-end', showConfirmButton:false, timer:2500, timerProgressBar:true})
 */
export const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
});

export function showToast(icon = 'success', title = '') {
    return Toast.fire({ icon, title });
}

/**
 * Modal konfirmasi (hapus dan tindakan berisiko):
 * TANPA timer, icon warning, tombol "Ya, ..."/ "Batal", reverseButtons,
 * buttonsStyling: false dengan kelas btn Bootstrap.
 */
export function confirmAction({
    title = 'Apakah Anda yakin?',
    text = '',
    confirmButtonText = 'Ya, lanjutkan',
    cancelButtonText = 'Batal',
    confirmButtonClass = 'btn btn-danger',
    cancelButtonClass = 'btn btn-secondary me-2',
    icon = 'warning',
} = {}) {
    return Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        reverseButtons: true,
        buttonsStyling: false,
        customClass: {
            confirmButton: confirmButtonClass,
            cancelButton: cancelButtonClass,
        },
    });
}

/**
 * Pengecualian aktivasi: Modal Swal TANPA timer berisi kode (huruf besar, monospace),
 * tombol "Salin" (navigator.clipboard) dan "Tutup", peringatan kode hanya tampil sekali.
 */
export function showActivationCodeModal(code, customerName = '') {
    const nameHtml = customerName ? `<p class="text-muted small mb-2">Pelanggan: <strong>${escapeHtml(customerName)}</strong></p>` : '';

    return Swal.fire({
        title: 'Kode Aktivasi Berhasil Dibuat',
        html: `
            ${nameHtml}
            <div class="p-3 my-3 bg-light border rounded text-center">
                <span class="fs-2 fw-bold font-monospace text-primary" style="letter-spacing: 2px;">${escapeHtml(code)}</span>
            </div>
            <div class="alert alert-warning py-2 px-3 small text-start mb-0">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <strong>Penting:</strong> Kode ini hanya ditampilkan sekali demi keamanan. Segera salin dan serahkan kepada pelanggan.
            </div>
        `,
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-clipboard me-1"></i> Salin Kode',
        cancelButtonText: 'Tutup',
        reverseButtons: true,
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-secondary me-2',
        },
        preConfirm: () => {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(() => {
                    showToast('success', 'Kode berhasil disalin ke clipboard');
                }).catch(() => {
                    showToast('info', 'Silakan salin kode secara manual');
                });
            } else {
                showToast('info', 'Silakan salin kode secara manual');
            }
            return false;
        },
    });
}

/**
 * Otomatis baca flash message dari layout session('success'|'error'|'warning')
 */
export function initFlashToasts() {
    const flashEl = document.getElementById('flash-data');
    if (!flashEl) return;

    const type = flashEl.dataset.type;
    const message = flashEl.dataset.message;
    const activationCode = flashEl.dataset.activationCode;
    const customerName = flashEl.dataset.customerName || '';

    if (activationCode) {
        showActivationCodeModal(activationCode, customerName);
    } else if (type && message) {
        showToast(type, message);
    }
}

export function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
