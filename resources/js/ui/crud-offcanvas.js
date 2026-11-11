import { Offcanvas } from 'bootstrap';
import { showToast, confirmAction } from './toast.js';

/**
 * Inisialisasi CRUD Offcanvas (Tambah & Ubah dalam 1 offcanvas)
 * @param {object} options
 */
export function initCrudOffcanvas(options = {}) {
    const offcanvasEl = document.querySelector(options.offcanvasSelector || '.crud-offcanvas');
    if (!offcanvasEl) return null;

    const bsOffcanvas = new Offcanvas(offcanvasEl);
    const form = offcanvasEl.querySelector('form');
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
        form.action = options.storeUrl;

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
            const res = await fetch(fetchUrl || `${options.baseUrl || ''}/${id}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!res.ok) {
                throw new Error('Gagal memuat data');
            }

            const data = await res.json();
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
            showToast('error', 'Gagal memuat rincian data untuk diubah');
        }
    }

    // Submit form via fetch
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearFormErrors();

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Menyimpan...';
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const formData = new FormData(form);

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

            const resJson = await res.json().catch(() => ({}));

            if (res.status === 422) {
                // Error validasi form
                const errors = resJson.errors || {};
                let firstInvalidEl = null;

                for (const [fieldName, messages] of Object.entries(errors)) {
                    const field = form.elements[fieldName];
                    const msg = Array.isArray(messages) ? messages[0] : messages;
                    if (field) {
                        field.classList.add('is-invalid');
                        if (!firstInvalidEl) firstInvalidEl = field;

                        let feedback = field.parentElement.querySelector('.invalid-feedback');
                        if (!feedback) {
                            feedback = document.createElement('div');
                            feedback.className = 'invalid-feedback';
                            field.parentElement.appendChild(feedback);
                        }
                        feedback.textContent = msg;
                        feedback.style.display = 'block';
                    }
                }

                if (firstInvalidEl) {
                    firstInvalidEl.focus();
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
            showToast('success', resJson.message || (isEditMode ? 'Data berhasil diperbarui.' : 'Data berhasil ditambahkan.'));

            if (typeof options.onSuccess === 'function') {
                options.onSuccess(resJson, isEditMode);
            }
        } catch (err) {
            showToast('error', err.message || 'Gagal menyimpan data.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalSubmitHtml;
            }
        }
    });

    // Helper Hapus Data dengan Swal konfirmasi + fetch DELETE
    async function deleteItem(deleteUrl, confirmMessage = 'Data yang dihapus tidak dapat dikembalikan.') {
        const result = await confirmAction({
            title: 'Hapus Data?',
            text: confirmMessage,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonClass: 'btn btn-danger',
        });

        if (!result.isConfirmed) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        try {
            const res = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
            });

            const resJson = await res.json().catch(() => ({}));

            if (!res.ok) {
                throw new Error(resJson.message || 'Gagal menghapus data.');
            }

            if (options.table && typeof options.table.ajax?.reload === 'function') {
                options.table.ajax.reload(null, false);
            }
            showToast('success', resJson.message || 'Data berhasil dihapus.');

            if (typeof options.onDeleteSuccess === 'function') {
                options.onDeleteSuccess(resJson);
            }
        } catch (err) {
            showToast('error', err.message || 'Gagal menghapus data.');
        }
    }

    return {
        openCreate,
        openEdit,
        deleteItem,
        hide: () => bsOffcanvas.hide(),
    };
}
