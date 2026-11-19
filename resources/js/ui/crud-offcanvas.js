import { Offcanvas, Modal } from 'bootstrap';
import { toast, confirmDialog } from './toast.js';

/**
 * Helper pembuka dan penutup modal/offcanvas tanpa instansiasi langsung di Blade
 */
export function showOffcanvas(selectorOrEl) {
    const el = typeof selectorOrEl === 'string' ? document.querySelector(selectorOrEl) : selectorOrEl;
    if (!el) return null;
    const instance = Offcanvas.getOrCreateInstance(el);
    instance.show();
    return instance;
}

export function hideOffcanvas(selectorOrEl) {
    const el = typeof selectorOrEl === 'string' ? document.querySelector(selectorOrEl) : selectorOrEl;
    if (!el) return null;
    const instance = Offcanvas.getInstance(el);
    if (instance) instance.hide();
    return instance;
}

export function showModal(selectorOrEl) {
    const el = typeof selectorOrEl === 'string' ? document.querySelector(selectorOrEl) : selectorOrEl;
    if (!el) return null;
    const instance = Modal.getOrCreateInstance(el);
    instance.show();
    return instance;
}

export function hideModal(selectorOrEl) {
    const el = typeof selectorOrEl === 'string' ? document.querySelector(selectorOrEl) : selectorOrEl;
    if (!el) return null;
    const instance = Modal.getInstance(el);
    if (instance) instance.hide();
    return instance;
}

/**
 * Helper fetch CRUD terpusat untuk operasi API agar Blade tidak memanggil fetch langsung
 */
export async function apiRequest(url, options = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const method = (options.method || 'GET').toUpperCase();
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || '',
        ...(options.headers || {}),
    };

    let body = options.body;
    if (body && typeof body === 'object' && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(body);
    } else if (typeof body === 'string' && !headers['Content-Type'] && (body.startsWith('{') || body.startsWith('['))) {
        headers['Content-Type'] = 'application/json';
    }

    const res = await fetch(url, {
        method,
        headers,
        body,
    });

    const resJson = await res.json().catch(() => ({}));

    if (!res.ok) {
        const error = new Error(resJson.message || `HTTP ${res.status}: Terjadi kesalahan pada server.`);
        error.status = res.status;
        error.data = resJson;
        throw error;
    }

    return resJson;
}

/**
 * Inisialisasi CRUD Offcanvas (Tambah & Ubah dalam 1 offcanvas)
 * @param {object} options
 */
export function initCrudOffcanvas(options = {}) {
    const offcanvasEl = document.querySelector(options.offcanvasSelector || '.offcanvas');
    if (!offcanvasEl) return null;

    const bsOffcanvas = Offcanvas.getOrCreateInstance(offcanvasEl);
    const form = offcanvasEl.querySelector('form');
    if (!form) return null;

    const titleEl = offcanvasEl.querySelector('.offcanvas-title');
    const submitBtn = offcanvasEl.querySelector('button[type="submit"]');
    const originalSubmitHtml = submitBtn ? submitBtn.innerHTML : 'Simpan';
    const methodInput = form.querySelector('input[name="_method"]');

    let isEditMode = false;
    let editId = null;

    // Bersihkan form & invalid feedback
    function clearFormErrors() {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
    }

    function resetForm() {
        form.reset();
        clearFormErrors();
        if (methodInput) methodInput.value = 'POST';
        isEditMode = false;
        editId = null;
    }

    // Event saat offcanvas tertutup
    offcanvasEl.addEventListener('hidden.bs.offcanvas', function () {
        resetForm();
    });

    // Buka mode Tambah
    function openCreate(extraData = {}) {
        resetForm();
        if (titleEl) titleEl.textContent = options.createTitle || 'Tambah Data';
        if (methodInput) methodInput.value = 'POST';
        if (options.storeUrl) form.action = options.storeUrl;

        // Jika ada extra data prefill (misal customer_id pada invoice)
        for (const [name, val] of Object.entries(extraData)) {
            const field = form.elements[name];
            if (field) field.value = val;
        }

        if (typeof options.onOpenCreate === 'function') {
            options.onOpenCreate(form, extraData);
        }

        bsOffcanvas.show();
    }

    // Buka mode Ubah
    async function openEdit(id, fetchUrl) {
        resetForm();
        isEditMode = true;
        editId = id;
        if (titleEl) titleEl.textContent = options.editTitle || 'Ubah Data';
        if (methodInput) methodInput.value = 'PUT';

        const updateUrlTemplate = options.updateUrl || `${options.baseUrl || ''}/${id}`;
        form.action = updateUrlTemplate.replace(':id', id);

        try {
            const data = await apiRequest(fetchUrl || `${options.baseUrl || ''}/${id}`);
            const record = data.data || data;

            // Isi nilai input form berdasarkan atribut name
            for (const key of Object.keys(record)) {
                const field = form.elements[key];
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = Boolean(record[key]);
                    } else if (field.type === 'radio') {
                        const radio = form.querySelector(`input[name="${key}"][value="${record[key]}"]`);
                        if (radio) radio.checked = true;
                    } else if (field.tagName === 'SELECT') {
                        field.value = record[key] !== null && record[key] !== undefined ? String(record[key]) : '';
                    } else {
                        field.value = record[key] !== null && record[key] !== undefined ? record[key] : '';
                    }
                }
            }

            if (typeof options.onOpenEdit === 'function') {
                options.onOpenEdit(form, record);
            }

            bsOffcanvas.show();
        } catch {
            toast('error', 'Gagal memuat rincian data untuk diubah');
        }
    }

    // Tampilkan error inline dan fokus ke elemen pertama
    function displayInlineErrors(errors) {
        let firstInvalidEl = null;

        for (const [fieldName, messages] of Object.entries(errors)) {
            let field = form.elements[fieldName];

            // Pemetaan nama field khusus seperti KTP dan Koordinat
            if (!field) {
                if (fieldName === 'ktp' || fieldName === 'ktp_file') {
                    field = form.elements['ktp_file'] || form.elements['ktp'] || form.querySelector('input[type="file"]');
                } else if (fieldName === 'latitude' || fieldName === 'longitude') {
                    field = form.elements['coordinates'] || form.elements[fieldName];
                } else {
                    field = form.querySelector(`[name="${fieldName}"]`);
                }
            }

            const msg = Array.isArray(messages) ? messages[0] : messages;
            if (field) {
                field.classList.add('is-invalid');
                if (!firstInvalidEl) firstInvalidEl = field;

                // Cari container feedback
                let feedback = field.parentElement?.querySelector('.invalid-feedback')
                    || document.getElementById(`err-${fieldName}`)
                    || document.getElementById(`err-${fieldName.replace('_', '-')}`);

                if (!feedback && field.parentElement) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    field.parentElement.appendChild(feedback);
                }

                if (feedback) {
                    feedback.textContent = msg;
                    feedback.style.display = 'block';
                }
            }
        }

        if (firstInvalidEl) {
            firstInvalidEl.focus();
        }
    }

    // Validasi file sisi klien sebelum pengiriman
    function validateClientFiles() {
        const fileInputs = form.querySelectorAll('input[type="file"]');
        const maxBytes = 3 * 1024 * 1024; // 3 MB
        const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        const allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        for (const input of fileInputs) {
            if (input.files && input.files.length > 0) {
                const file = input.files[0];

                // Cek ukuran berkas
                if (file.size > maxBytes) {
                    input.classList.add('is-invalid');
                    let feedback = input.parentElement?.querySelector('.invalid-feedback')
                        || document.getElementById(`err-${input.name}`)
                        || document.getElementById(`err-${input.name?.replace('_', '-')}`);

                    if (feedback) {
                        feedback.textContent = 'Ukuran berkas melebihi batas 3 MB.';
                        feedback.style.display = 'block';
                    }
                    input.focus();
                    try {
                        toast('warning', 'Ukuran berkas KTP melebihi batas 3 MB.');
                    } catch (e) {
                        console.warn(e);
                    }
                    return false;
                }

                // Cek tipe berkas
                const ext = file.name.split('.').pop()?.toLowerCase();
                const mimeValid = file.type ? allowedMimes.includes(file.type) : true;
                const extValid = ext ? allowedExtensions.includes(ext) : false;

                if (!extValid && !mimeValid) {
                    input.classList.add('is-invalid');
                    let feedback = input.parentElement?.querySelector('.invalid-feedback')
                        || document.getElementById(`err-${input.name}`)
                        || document.getElementById(`err-${input.name?.replace('_', '-')}`);

                    if (feedback) {
                        feedback.textContent = 'Format berkas harus JPG, PNG, atau WebP.';
                        feedback.style.display = 'block';
                    }
                    input.focus();
                    try {
                        toast('warning', 'Format berkas harus JPG, PNG, atau WebP.');
                    } catch (e) {
                        console.warn(e);
                    }
                    return false;
                }
            }
        }

        return true;
    }

    // Submit form via fetch
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearFormErrors();

        // Cek validasi berkas di sisi klien terlebih dahulu
        if (!validateClientFiles()) {
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Menyimpan...';
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const formData = new FormData(form);

        // Pastikan checkbox non-disabled selalu mengirim boolean eksplisit ('1' atau '0')
        form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            if (cb.name && !cb.disabled) {
                formData.set(cb.name, cb.checked ? '1' : '0');
            }
        });

        // Jika mode edit, tambahkan _method = PUT jika belum ada
        if (isEditMode && !formData.has('_method')) {
            formData.append('_method', 'PUT');
        }

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: formData,
            });

            // Tangani HTTP 413 Payload Too Large
            if (res.status === 413) {
                const fileInput = form.querySelector('input[type="file"]');
                if (fileInput) {
                    fileInput.classList.add('is-invalid');
                    let feedback = fileInput.parentElement?.querySelector('.invalid-feedback')
                        || document.getElementById(`err-${fileInput.name}`)
                        || document.getElementById(`err-${fileInput.name?.replace('_', '-')}`);
                    if (feedback) {
                        feedback.textContent = 'Ukuran berkas melebihi batas server.';
                        feedback.style.display = 'block';
                    }
                    fileInput.focus();
                }
                try {
                    toast('error', 'Ukuran berkas melebihi batas server.');
                } catch (e) {
                    console.warn(e);
                }
                return;
            }

            const resJson = await res.json().catch(() => ({}));

            if (res.status === 422) {
                // Tampilkan error inline SEBELUM toast
                displayInlineErrors(resJson.errors || {});

                // Toast ringkas bertipe warning; kegagalan toast tidak boleh hentikan tampilan inline
                try {
                    toast('warning', resJson.message || 'Mohon periksa kembali isian formulir Anda.');
                } catch (toastErr) {
                    console.warn('[Toast Error]', toastErr);
                }
                return;
            }

            if (!res.ok) {
                throw new Error(resJson.message || 'Terjadi kesalahan pada server.');
            }

            // Sukses
            bsOffcanvas.hide();
            if (options.table && typeof options.table.ajax?.reload === 'function') {
                options.table.ajax.reload(null, false);
            }
            toast('success', resJson.message || (isEditMode ? 'Data berhasil diperbarui.' : 'Data berhasil ditambahkan.'));

            if (typeof options.onSuccess === 'function') {
                options.onSuccess(resJson, isEditMode);
            }
        } catch (err) {
            toast('error', err.message || 'Gagal menyimpan data.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalSubmitHtml;
            }
        }
    });

    // Helper Hapus Data dengan Swal konfirmasi + fetch DELETE
    async function deleteItem(deleteUrl, confirmMessage = 'Data yang dihapus tidak dapat dikembalikan.') {
        const result = await confirmDialog({
            title: 'Hapus Data?',
            text: confirmMessage,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonClass: 'btn btn-danger',
        });

        if (!result.isConfirmed) return;

        try {
            const resJson = await apiRequest(deleteUrl, { method: 'DELETE' });

            if (options.table && typeof options.table.ajax?.reload === 'function') {
                options.table.ajax.reload(null, false);
            }
            toast('success', resJson.message || 'Data berhasil dihapus.');

            if (typeof options.onDeleteSuccess === 'function') {
                options.onDeleteSuccess(resJson);
            }
        } catch (err) {
            toast('error', err.message || 'Gagal menghapus data.');
        }
    }

    return {
        openCreate,
        openEdit,
        deleteItem,
        hide: () => bsOffcanvas.hide(),
        show: () => bsOffcanvas.show(),
    };
}
