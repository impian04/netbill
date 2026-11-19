/**
 * filter-state.js — Fase 17k
 * Manajemen state filter tersentralisasi, sinkronisasi URL (replaceState & popstate),
 * pembatalan request (AbortController), debounce pencarian, dan integrasi tombol ekspor.
 */

/**
 * Peta alias nama parameter agar seragam di URL dan backend
 */
export const PARAM_ALIASES = {
    search: 'q',
    date_from: 'from',
    start_date: 'from',
    date_to: 'to',
    end_date: 'to',
    service_status: 'status',
};

/**
 * Normalisasi nama parameter input ke nama pendek baku di URL
 */
export function normalizeParamName(name) {
    return PARAM_ALIASES[name] || name;
}

/**
 * Baca seluruh parameter dari query string URL saat ini
 * @returns {Record<string, string>}
 */
export function getUrlParams() {
    const params = {};
    const searchParams = new URLSearchParams(window.location.search);
    for (const [key, value] of searchParams.entries()) {
        const trimmed = value.trim();
        if (trimmed !== '') {
            params[key] = trimmed;
        }
    }
    return params;
}

/**
 * Resolusi elemen form atau container pembungkus kontrol filter
 * @param {HTMLFormElement|HTMLElement|string} target
 * @returns {HTMLElement|null}
 */
export function resolveFormElement(target) {
    if (!target) return null;
    if (typeof target === 'string') {
        const el = document.querySelector(target);
        if (!el) return null;
        return el instanceof HTMLFormElement ? el : (el.querySelector('form') || el);
    }
    if (target instanceof HTMLFormElement) return target;
    if (target instanceof HTMLElement) {
        return target.querySelector('form') || target;
    }
    return null;
}

/**
 * Sinkronkan form filter dari parameter URL saat inisialisasi halaman (hydration)
 * @param {HTMLFormElement|HTMLElement|string} formEl
 * @returns {boolean} Apakah ada nilai yang di-hydrate dari URL
 */
export function hydrateFormFromUrl(formEl) {
    const el = resolveFormElement(formEl);
    if (!el) return false;

    const urlParams = getUrlParams();
    let hasValue = false;

    const elements = el instanceof HTMLFormElement ? Array.from(el.elements) : Array.from(el.querySelectorAll('input, select, textarea'));

    elements.forEach(element => {
        if (!element.name || element.type === 'submit' || element.type === 'reset' || element.type === 'button') {
            return;
        }

        const paramName = normalizeParamName(element.name);
        const val = urlParams[paramName] ?? urlParams[element.name];

        if (val !== undefined && val !== null) {
            if (element.type === 'checkbox') {
                element.checked = (val === '1' || val === 'true' || val === element.value);
            } else if (element.type === 'radio') {
                element.checked = (element.value === val);
            } else {
                element.value = val;
            }
            hasValue = true;
        }
    });

    return hasValue;
}

/**
 * Ambil data filter aktif dari form (hanya yang tidak kosong)
 * @param {HTMLFormElement|HTMLElement|string} formEl
 * @returns {Record<string, string>}
 */
export function getActiveFilterData(formEl) {
    const el = resolveFormElement(formEl);
    if (!el) return {};

    const data = {};
    if (el instanceof HTMLFormElement) {
        const formData = new FormData(el);
        for (const [key, value] of formData.entries()) {
            const trimmed = typeof value === 'string' ? value.trim() : value;
            if (trimmed !== '') {
                data[key] = trimmed;
            }
        }
    } else {
        const inputs = el.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (!input.name || input.disabled || input.type === 'submit' || input.type === 'reset' || input.type === 'button') return;
            if (input.type === 'checkbox' || input.type === 'radio') {
                if (input.checked) data[input.name] = input.value;
            } else if (input.value && input.value.trim() !== '') {
                data[input.name] = input.value.trim();
            }
        });
    }

    return data;
}

/**
 * Perbarui query string URL di browser memakai history.replaceState
 * @param {Record<string, string>} filterData
 */
export function updateUrlQuery(filterData) {
    const searchParams = new URLSearchParams();

    for (const [key, val] of Object.entries(filterData)) {
        if (val !== null && val !== undefined && String(val).trim() !== '') {
            const shortKey = normalizeParamName(key);
            searchParams.set(shortKey, String(val).trim());
        }
    }

    const queryString = searchParams.toString();
    const newUrl = queryString ? `${window.location.pathname}?${queryString}` : window.location.pathname;

    // Perbarui URL browser tanpa reload halaman
    window.history.replaceState({ filters: filterData }, '', newUrl);

    // Sinkronkan tombol ekspor jika ada
    syncExportButtons(searchParams);
}

/**
 * Sinkronkan tombol ekspor dengan parameter query saat ini
 * @param {URLSearchParams} searchParams
 */
export function syncExportButtons(searchParams) {
    const exportSelectors = [
        '#btn-export',
        '#btn-export-customers',
        '#btn-export-invoices',
        '#btn-export-all-invoices',
        '#btn-export-tickets',
        '#btn-export-installation',
        '#btn-export-installation-wo',
        '#btn-export-fees',
        '[data-export-btn]',
    ];
    exportSelectors.forEach(sel => {
        document.querySelectorAll(sel).forEach(btn => {
            if (btn.tagName === 'A' && btn.href) {
                const url = new URL(btn.href, window.location.origin);
                url.search = searchParams.toString();
                btn.href = url.toString();
            }
        });
    });
}

/**
 * Inisialisasi pengelola state filter interaktif untuk suatu form
 * @param {object} options
 *   - formSelector {string|HTMLFormElement} Selector form filter
 *   - onFilterChange {function(filterData)} Callback saat filter berubah
 *   - onReset {function()} Callback saat filter di-reset
 *   - isRateLimited {boolean} Apakah pencarian dibatasi laju (seperti NOC, butuh enter/submit manual)
 * @returns {object} Controller instance
 */
export function initFilterState(options = {}) {
    const formEl = resolveFormElement(options.formSelector || '#filterForm');

    if (!formEl) {
        return null;
    }

    let activeAbortController = null;
    let debounceTimer = null;
    const isRateLimited = !!options.isRateLimited;

    // Helper untuk membatalkan request ajax/fetch sebelumnya yang belum selesai
    function abortPreviousRequest() {
        if (activeAbortController) {
            try {
                activeAbortController.abort();
            } catch (e) {
                // Ignore abort error
            }
            activeAbortController = null;
        }
    }

    function createAbortSignal() {
        abortPreviousRequest();
        activeAbortController = new AbortController();
        return activeAbortController.signal;
    }

    // 1. Hydrate kontrol dari URL saat pertama kali halaman dimuat
    const hadInitialUrlParams = hydrateFormFromUrl(formEl);
    const initialFilters = getActiveFilterData(formEl);
    updateUrlQuery(initialFilters);

    // 2. Fungsi trigger perubahan filter
    function triggerChange() {
        const filters = getActiveFilterData(formEl);
        updateUrlQuery(filters);

        if (typeof options.onFilterChange === 'function') {
            options.onFilterChange(filters, createAbortSignal());
        }
    }

    // 3. Listener perubahan input teks pencarian (debounce 400 ms)
    const textInputs = formEl.querySelectorAll('input[type="text"], input[type="search"]');
    textInputs.forEach(input => {
        if (isRateLimited) {
            // Pencarian rate-limited: hanya bereaksi saat Enter ditekan (min 3 karakter)
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const val = input.value.trim();
                    if (val.length === 0 || val.length >= 3) {
                        triggerChange();
                    }
                }
            });
        } else {
            // Instant debounce: 400ms, minimal 2 karakter atau kosong
            input.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const val = input.value.trim();
                    if (val.length === 0 || val.length >= 2) {
                        triggerChange();
                    }
                }, 400);
            });
        }
    });

    // 4. Listener perubahan select, date, month, checkbox, radio (berlaku seketika)
    const instantControls = formEl.querySelectorAll('select, input[type="date"], input[type="month"], input[type="checkbox"], input[type="radio"]');
    instantControls.forEach(ctrl => {
        ctrl.addEventListener('change', () => {
            triggerChange();
        });
    });

    // Cegah submit bawaan form yang memuat ulang halaman
    formEl.addEventListener('submit', (e) => {
        e.preventDefault();
        triggerChange();
    });

    // 5. Listener tombol Reset
    const resetBtn = formEl.querySelector('button[type="reset"], [id*="reset"], .btn-reset-filter');
    if (resetBtn) {
        resetBtn.addEventListener('click', (e) => {
            e.preventDefault();

            // Cek apakah form & URL saat ini sudah bersih/kosong
            const currentFilters = getActiveFilterData(formEl);
            const hasExistingParams = Object.keys(currentFilters).length > 0 || window.location.search !== '';

            // Jika sudah kosong, jangan buat permintaan tambahan
            if (!hasExistingParams) {
                return;
            }

            // Kosongkan kontrol form
            formEl.reset();
            Array.from(formEl.elements).forEach(el => {
                if (el.tagName === 'INPUT' && (el.type === 'text' || el.type === 'search' || el.type === 'date' || el.type === 'month')) {
                    el.value = '';
                } else if (el.tagName === 'SELECT') {
                    el.selectedIndex = 0;
                    el.value = '';
                } else if (el.type === 'checkbox' || el.type === 'radio') {
                    el.checked = false;
                }
            });

            // Hapus parameter URL
            updateUrlQuery({});

            if (typeof options.onReset === 'function') {
                options.onReset(createAbortSignal());
            } else if (typeof options.onFilterChange === 'function') {
                options.onFilterChange({}, createAbortSignal());
            }
        });
    }

    // 6. Listener popstate (Back/Forward browser)
    window.addEventListener('popstate', (e) => {
        hydrateFormFromUrl(formEl);
        const popFilters = getActiveFilterData(formEl);
        syncExportButtons(new URLSearchParams(window.location.search));

        if (typeof options.onFilterChange === 'function') {
            options.onFilterChange(popFilters, createAbortSignal());
        }
    });

    return {
        formEl,
        getFilters: () => getActiveFilterData(formEl),
        triggerChange,
        abortPreviousRequest,
        hadInitialUrlParams,
    };
}

/**
 * Helper untuk halaman daftar kartu (card list) yang menggunakan reload partial via fetch.
 * Memisahkan logika fetch dari view Blade agar mematuhi konvensi UI.
 *
 * @param {string} formSelector
 * @param {string} containerSelector
 * @param {string} baseUrl
 */
export function initCardFilterPartial(formSelector, containerSelector, baseUrl) {
    const container = document.querySelector(containerSelector);
    if (!container) return;

    async function reloadCards(filters, signal) {
        const params = new URLSearchParams();
        for (const [k, v] of Object.entries(filters)) {
            if (v) params.set(k, v);
        }
        const fullUrl = `${baseUrl}${params.toString() ? '?' + params.toString() : ''}`;
        try {
            const resp = await fetch(fullUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: signal,
            });
            if (!resp.ok) return;
            const html = await resp.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newContent = doc.querySelector(containerSelector);
            if (newContent && container) {
                container.innerHTML = newContent.innerHTML;
            }
        } catch (e) {
            if (e.name !== 'AbortError') console.error(e);
        }
    }

    return initFilterState({
        formSelector: formSelector,
        onFilterChange: reloadCards,
        onReset: (signal) => reloadCards({}, signal),
    });
}


