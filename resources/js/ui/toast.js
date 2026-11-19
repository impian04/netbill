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
    customClass: {
        popup: 'nb-swal-popup',
    },
});

const VALID_TOAST_TYPES = ['success', 'error', 'warning', 'info', 'question'];

/**
 * Helper canonical toast(type, message)
 * @param {string} type - success | error | warning | info | question
 * @param {string} message - teks pesan toast
 */
export function toast(type = 'info', message = '') {
    // Pengaman jika parameter terbalik: toast(message, type)
    if (VALID_TOAST_TYPES.includes(message) && !VALID_TOAST_TYPES.includes(type)) {
        const tmp = type;
        type = message;
        message = tmp;
    }

    let resolvedType = type;
    if (!VALID_TOAST_TYPES.includes(resolvedType)) {
        console.warn(`[Toast] Invalid toast type "${type}". Falling back to "info".`);
        resolvedType = 'info';
    }

    return Toast.fire({ icon: resolvedType, title: String(message || '') });
}

export const showToast = toast;

/**
 * Helper untuk mendapatkan elemen offcanvas yang sedang aktif/terbuka.
 * Ini memastikan SweetAlert2 merender modal ke dalam target offcanvas
 * sehingga Bootstrap focus trap terpenuhi dan tidak ada peringatan aria-hidden.
 */
export function getActiveOffcanvas() {
    return document.querySelector('.offcanvas.show');
}

/**
 * Modal konfirmasi (hapus dan tindakan berisiko)
 * Menggunakan opsi target ke offcanvas terbuka (jika ada).
 */
export function confirmDialog({
    title = 'Apakah Anda yakin?',
    text = '',
    confirmButtonText = 'Ya, lanjutkan',
    cancelButtonText = 'Batal',
    confirmButtonClass = 'btn btn-sm btn-danger',
    cancelButtonClass = 'btn btn-sm btn-outline-secondary me-2',
    icon = 'warning',
} = {}) {
    const activeOffcanvas = getActiveOffcanvas();

    return Swal.fire({
        target: activeOffcanvas || 'body',
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        reverseButtons: true,
        buttonsStyling: false,
        customClass: {
            popup: 'nb-swal-popup',
            confirmButton: confirmButtonClass,
            cancelButton: cancelButtonClass,
        },
    });
}

export const confirmAction = confirmDialog;

/**
 * Prompt dialog untuk meminta alasan / catatan verifikasi
 * Menggunakan opsi target ke offcanvas terbuka (jika ada).
 */
export function promptReason({
    title = 'Masukkan Alasan',
    text = '',
    inputLabel = '',
    inputPlaceholder = 'Ketik alasan / catatan di sini...',
    confirmButtonText = 'Kirim',
    cancelButtonText = 'Batal',
    confirmButtonClass = 'btn btn-sm btn-primary',
    icon = 'question',
    required = true,
    requiredMessage = 'Alasan wajib diisi.',
} = {}) {
    const activeOffcanvas = getActiveOffcanvas();

    return Swal.fire({
        target: activeOffcanvas || 'body',
        title,
        text,
        input: 'textarea',
        inputLabel,
        inputPlaceholder,
        icon,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        reverseButtons: true,
        buttonsStyling: false,
        customClass: {
            popup: 'nb-swal-popup',
            confirmButton: confirmButtonClass,
            cancelButton: 'btn btn-sm btn-outline-secondary me-2',
        },
        inputValidator: (value) => {
            if (required && (!value || !value.trim())) {
                return requiredMessage;
            }
            return null;
        },
    });
}

/**
 * Pengecualian aktivasi: Modal Swal TANPA timer berisi kode (huruf besar, monospace),
 * tombol "Salin" (navigator.clipboard) dan "Tutup", peringatan kode hanya tampil sekali.
 */
export function showActivationCodeModal(code, customerName = '') {
    const nameHtml = customerName ? `<p class="text-muted small mb-2">Pelanggan: <strong>${escapeHtml(customerName)}</strong></p>` : '';
    const activeOffcanvas = getActiveOffcanvas();

    return Swal.fire({
        target: activeOffcanvas || 'body',
        title: 'Kode Aktivasi Berhasil Dibuat',
        html: `
            ${nameHtml}
            <div class="p-3 my-3 bg-light border rounded text-center">
                <span class="fs-2 fw-bold font-monospace text-primary" style="letter-spacing: 2px;">${escapeHtml(code)}</span>
            </div>
            <div class="alert alert-warning py-2 px-3 small text-start mb-0">
                <strong>Penting:</strong> Kode ini hanya ditampilkan sekali demi keamanan. Segera salin dan serahkan kepada pelanggan.
            </div>
        `,
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Salin Kode',
        cancelButtonText: 'Tutup',
        reverseButtons: true,
        buttonsStyling: false,
        customClass: {
            popup: 'nb-swal-popup',
            confirmButton: 'btn btn-sm btn-primary',
            cancelButton: 'btn btn-sm btn-outline-secondary me-2',
        },
        preConfirm: () => {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(() => {
                    toast('success', 'Kode berhasil disalin ke clipboard');
                }).catch(() => {
                    toast('info', 'Silakan salin kode secara manual');
                });
            } else {
                toast('info', 'Silakan salin kode secara manual');
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
        toast(type, message);
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

export function showPasswordLinkModal(url, staffName = '') {
    const nameHtml = staffName ? `<p class="text-muted small mb-2 text-start">Staf: <strong>${escapeHtml(staffName)}</strong></p>` : '';
    const activeOffcanvas = getActiveOffcanvas();

    return Swal.fire({
        target: activeOffcanvas || 'body',
        title: 'Tautan Set Password',
        html: `
            ${nameHtml}
            <p class="small text-muted mb-3 text-start">
                Tautan set password berhasil dibuat. Salin tautan ini dan kirimkan langsung kepada staf bersangkutan. Tautan hanya berlaku 24 jam dan dapat digunakan 1 kali.
            </p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" id="swal-set-url" class="form-control form-control-sm font-monospace" value="${escapeHtml(url)}" readonly>
                <button class="btn btn-outline-primary btn-sm" type="button" id="btn-copy-set-url">
                    Salin
                </button>
            </div>
            <div id="copy-success-badge" class="small text-success mt-1 text-start" style="display:none;">
                Tautan berhasil disalin ke clipboard!
            </div>
            <div class="alert alert-warning py-2 px-3 small text-start mt-3 mb-0">
                <strong>Penting:</strong> Tautan ini hanya ditampilkan sekali demi keamanan.
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        showConfirmButton: false,
        cancelButtonText: 'Tutup',
        cancelButtonClass: 'btn btn-sm btn-outline-secondary',
        buttonsStyling: false,
        allowOutsideClick: false,
        didOpen: () => {
            document.getElementById('btn-copy-set-url')?.addEventListener('click', () => {
                const input = document.getElementById('swal-set-url');
                if (input) {
                    input.select();
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(input.value).then(() => {
                            const badge = document.getElementById('copy-success-badge');
                            if (badge) badge.style.display = 'block';
                            toast('success', 'Tautan disalin ke clipboard');
                        }).catch(() => {
                            toast('info', 'Silakan salin tautan secara manual');
                        });
                    } else {
                        document.execCommand('copy');
                        const badge = document.getElementById('copy-success-badge');
                        if (badge) badge.style.display = 'block';
                        toast('success', 'Tautan disalin ke clipboard');
                    }
                }
            });
        }
    });
}

export { Swal };
