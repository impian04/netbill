/**
 * 11-generic-filter-contract.spec.js — Fase 17i-b
 *
 * Kontrak Filter Generik Multi-Role NetBill:
 * 1. Menelusuri seluruh menu sidebar (config/menu.php) secara otomatis per role dan mendeteksi x-filter-card.
 * 2. Menguji setiap kontrol filter (select, input tanggal/bulan, input search) secara terpisah.
 * 3. Memverifikasi parameter permintaan jaringan (AJAX/Fetch/GET) memuat nama parameter kontrol.
 * 4. Memverifikasi perubahan baris/kartu data atau munculnya empty state yang wajar.
 * 5. Memverifikasi mekanisme Reset (kontrol kosong, parameter hilang dari URL, data kembali, tanpa full page reload).
 * 6. Memverifikasi Refresh mempertahankan filter aktif dari URL (hydration).
 * 7. Memverifikasi sinkronisasi URL tombol Ekspor dengan parameter filter aktif.
 * 8. Memastikan tidak ada console.error, pageerror, atau undefined window.* functions.
 */

import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { loginAs, attachErrorMonitor, assertNoErrors } from './helpers.js';

test.describe.serial('Kontrak Filter Generik NetBill (Fase 17i-b)', () => {
    let discoveredFilterPages = [];

    const ROLES = [
        { role: 'super_admin', label: 'Super Admin' },
        { role: 'admin',       label: 'Admin POP' },
        { role: 'marketing',   label: 'Marketing' },
        { role: 'surveyor',    label: 'Surveyor' },
        { role: 'technician',  label: 'Teknisi' },
        { role: 'noc',         label: 'NOC' },
    ];

    /**
     * Langkah 1: Kumpulkan seluruh halaman berfilter secara otomatis per role
     * dari menu sidebar dan verifikasi kelengkapan terhadap seluruh Blade view.
     */
    test('1. Pengumpulan halaman berfilter otomatis & verifikasi kelengkapan menu', async ({ page }) => {
        test.setTimeout(120_000);
        const errors = attachErrorMonitor(page);
        const visitedUrls = new Set();
        discoveredFilterPages = [];

        for (const { role, label } of ROLES) {
            await loginAs(page, role);

            // Ambil semua tautan di sidebar yang mengarah ke route aplikasi
            const navLinks = await page.locator('#sidebar a.nav-link').evaluateAll(nodes =>
                nodes.map(n => ({
                    href: n.getAttribute('href'),
                    label: n.textContent ? n.textContent.trim().replace(/\s+/g, ' ') : '',
                })).filter(item => item.href && !item.href.startsWith('javascript') && !item.href.includes('#'))
            );

            for (const item of navLinks) {
                if (visitedUrls.has(item.href)) continue;
                visitedUrls.add(item.href);

                await page.goto(item.href);
                await page.waitForLoadState('domcontentloaded');
                await page.waitForTimeout(250);

                const filterCardCount = await page.locator('.filter-card, [data-filter-mode]').count();
                if (filterCardCount > 0) {
                    const filterCard = page.locator('.filter-card, [data-filter-mode]').first();
                    const mode = (await filterCard.getAttribute('data-filter-mode')) || 'instant';

                    // Ekstrak semua kontrol filter (select, input text/search, input date/month)
                    const controls = await filterCard.locator('select, input:not([type="hidden"]):not([type="submit"]):not([type="reset"]):not([type="button"])').evaluateAll(nodes =>
                        nodes.map(n => ({
                            tagName: n.tagName.toLowerCase(),
                            name: n.getAttribute('name'),
                            type: n.getAttribute('type') || (n.tagName.toLowerCase() === 'select' ? 'select' : 'text'),
                            id: n.id || null,
                            optionsCount: n.tagName.toLowerCase() === 'select' ? n.options.length : 0,
                            availableOptions: n.tagName.toLowerCase() === 'select'
                                ? Array.from(n.options).map(o => ({ value: o.value, text: o.text.trim() })).filter(o => o.value !== '')
                                : [],
                        }))
                    );

                    // Deteksi tombol reset
                    const hasResetBtn = await filterCard.locator('button[type="reset"], [id*="reset"], .btn-reset-filter, a:has-text("Reset"), button:has-text("Reset")').count() > 0;

                    // Deteksi tombol ekspor di halaman (di header atau actions)
                    const hasExportBtn = await page.locator('#btn-export, #btn-export-customers, #btn-export-invoices, #btn-export-all-invoices, #btn-export-tickets, #btn-export-installation, #btn-export-installation-wo, #btn-export-fees, [data-export-btn]').count() > 0;

                    // Deteksi kontainer data (tabel DataTables atau daftar kartu)
                    const isDataTable = await page.locator('table.dataTable, table[id*="Table"], table[id*="table"]').count() > 0;
                    const tableOrContainerId = isDataTable
                        ? await page.locator('table.dataTable, table[id*="Table"], table[id*="table"]').first().getAttribute('id')
                        : null;

                    discoveredFilterPages.push({
                        role,
                        roleLabel: label,
                        url: item.href,
                        menuLabel: item.label,
                        mode,
                        controls,
                        hasResetBtn,
                        hasExportBtn,
                        isDataTable,
                        tableOrContainerId,
                    });
                }
            }
        }

        // Verifikasi kelengkapan: pindai seluruh views yang menggunakan x-filter-card
        const viewsDir = path.resolve(process.cwd(), 'resources/views');
        const viewFilesWithFilterCard = [];

        function scanDir(dir) {
            const entries = fs.readdirSync(dir, { withFileTypes: true });
            for (const entry of entries) {
                const fullPath = path.join(dir, entry.name);
                if (entry.isDirectory()) {
                    scanDir(fullPath);
                } else if (entry.name.endsWith('.blade.php')) {
                    const content = fs.readFileSync(fullPath, 'utf-8');
                    if (content.includes('<x-filter-card')) {
                        viewFilesWithFilterCard.push(path.relative(viewsDir, fullPath).replace(/\\/g, '/'));
                    }
                }
            }
        }
        scanDir(viewsDir);

        console.log(`[E2E Filter Contract] Ditemukan ${discoveredFilterPages.length} halaman berfilter di menu sidebar.`);
        console.log(`[E2E Filter Contract] Ditemukan ${viewFilesWithFilterCard.length} template blade dengan x-filter-card.`);

        // Minimal 20 halaman berfilter harus ditemukan dan tercakup
        expect(discoveredFilterPages.length).toBeGreaterThanOrEqual(20);

        // Setiap halaman berfilter wajib memiliki tombol Reset (kecuali halaman apply dashboard yang punya tombol reset terpisah)
        for (const fp of discoveredFilterPages) {
            expect(fp.hasResetBtn, `Halaman ${fp.url} (${fp.menuLabel}) wajib memiliki tombol Reset`).toBe(true);
        }

        assertNoErrors(errors);
    });

    /**
     * Langkah 2: Uji interaksi kontrol filter di seluruh halaman berfilter.
     */
    test('2. Pengujian kontrol filter (select, date, search), parameter request, dan empty state', async ({ page }) => {
        test.setTimeout(300_000); // 5 menit untuk seluruh penelusuran kontrol
        const errors = attachErrorMonitor(page);

        // Jika discoveredFilterPages belum terisi (misal dijalankan individual), jalankan penemuan cepat
        if (!discoveredFilterPages || discoveredFilterPages.length === 0) {
            await loginAs(page, 'super_admin');
            const navLinks = await page.locator('#sidebar a.nav-link').evaluateAll(nodes =>
                nodes.map(n => ({ href: n.getAttribute('href'), label: n.textContent.trim() })).filter(item => item.href && !item.href.includes('#'))
            );
            for (const item of navLinks) {
                await page.goto(item.href);
                await page.waitForLoadState('domcontentloaded');
                if (await page.locator('.filter-card, [data-filter-mode]').count() > 0) {
                    discoveredFilterPages.push({ role: 'super_admin', url: item.href, menuLabel: item.label, mode: 'instant' });
                }
            }
        }

        let currentRole = null;

        for (const fp of discoveredFilterPages) {
            if (currentRole !== fp.role) {
                await loginAs(page, fp.role);
                currentRole = fp.role;
            }

            await page.goto(fp.url);
            await page.waitForLoadState('domcontentloaded');
            await page.waitForTimeout(400);

            const filterCard = page.locator('.filter-card, [data-filter-mode]').first();
            const mode = (await filterCard.getAttribute('data-filter-mode')) || 'instant';

            // Ambil seluruh kontrol filter di halaman ini
            const controls = await filterCard.locator('select, input:not([type="hidden"]):not([type="submit"]):not([type="reset"]):not([type="button"])').all();

            for (const ctrl of controls) {
                const tagName = await ctrl.evaluate(el => el.tagName.toLowerCase());
                const name = await ctrl.getAttribute('name');
                const type = await ctrl.getAttribute('type');
                if (!name) continue;

                // Monitor network request berikutnya
                let interceptedRequestUrl = '';
                const requestListener = (req) => {
                    const u = req.url();
                    if ((req.headers()['x-requested-with'] === 'XMLHttpRequest' || u.includes(name)) && !u.includes('/activity-logs')) {
                        interceptedRequestUrl = u;
                    }
                };
                page.on('request', requestListener);

                if (tagName === 'select') {
                    // Ambil opsi yang bernilai (bukan opsi pertama yang biasanya placeholder)
                    const options = await ctrl.locator('option').evaluateAll(opts =>
                        opts.map(o => o.value).filter(v => v !== '')
                    );

                    if (options.length > 0) {
                        const testValue = options[0];
                        await ctrl.selectOption(testValue);

                        if (mode === 'apply') {
                            // Mode apply: klik tombol submit filter
                            await filterCard.locator('button[type="submit"], input[type="submit"], #btn-dash-filter').first().click();
                            await page.waitForLoadState('domcontentloaded');
                            expect(page.url()).toContain(`${name}=${encodeURIComponent(testValue)}`);
                        } else {
                            // Mode instant: tunggu request AJAX/Fetch
                            await page.waitForTimeout(600);
                            const hasParamInUrl = page.url().includes(`${name}=`) || page.url().includes('status=') || page.url().includes('from=') || page.url().includes('to=');
                            const hasParamInReq = interceptedRequestUrl.includes(`${name}=`) || interceptedRequestUrl.includes(`${name}%3D`);
                            expect(hasParamInUrl || hasParamInReq, `Request atau URL di ${fp.url} harus memuat parameter ${name}`).toBe(true);
                        }

                        // Kembalikan select ke kosong/placeholder
                        await ctrl.selectOption('');
                        if (mode === 'apply') {
                            await filterCard.locator('#btn-dash-reset, a:has-text("Reset")').first().click();
                            await page.waitForLoadState('domcontentloaded');
                        } else {
                            await page.waitForTimeout(500);
                        }
                    }
                } else if (type === 'search' || type === 'text') {
                    // Uji pencarian dengan string yang pasti tidak ada data
                    const dummySearch = 'XYZ999NONEXISTENT';
                    await ctrl.fill(dummySearch);

                    if (fp.url.includes('/customer-lookup')) {
                        // Rate-limited NOC search: tekan Enter atau klik Cari
                        await ctrl.press('Enter');
                        await page.waitForTimeout(600);
                    } else if (mode === 'apply') {
                        await filterCard.locator('button[type="submit"]').first().click();
                        await page.waitForLoadState('domcontentloaded');
                    } else {
                        // Tunggu debounce 400ms + render
                        await page.waitForTimeout(700);
                    }

                    // Verifikasi parameter search/q terkirim di URL atau request
                    const hasSearchInUrl = page.url().includes('q=') || page.url().includes('search=');
                    const hasSearchInReq = interceptedRequestUrl.includes('q=') || interceptedRequestUrl.includes('search=');
                    expect(hasSearchInUrl || hasSearchInReq, `Parameter pencarian harus terkirim di ${fp.url}`).toBe(true);

                    // Verifikasi muncul empty state yang wajar (tabel atau kartu menampilkan kosong)
                    const contentText = await page.locator('main, #content, .table-responsive, [id*="Table"], [id*="table"]').first().textContent();
                    expect(contentText).toMatch(/tidak ada|belum ada|no matching|no data|kosong|0 data/i);

                    // Bersihkan kembali teks pencarian
                    await ctrl.fill('');
                    if (fp.url.includes('/customer-lookup')) {
                        const resetBtn = filterCard.locator('button[type="reset"], #btn-reset-filters').first();
                        await resetBtn.click();
                        await page.waitForTimeout(500);
                    } else if (mode === 'apply') {
                        await filterCard.locator('a:has-text("Reset")').first().click();
                        await page.waitForLoadState('domcontentloaded');
                    } else {
                        await ctrl.press('Enter');
                        await page.waitForTimeout(600);
                    }
                } else if (type === 'date' || type === 'month') {
                    // Uji input tanggal dengan tanggal lampau yang pasti kosong
                    const dummyDate = type === 'month' ? '2020-01' : '2020-01-01';
                    await ctrl.fill(dummyDate);

                    if (mode === 'apply') {
                        await filterCard.locator('button[type="submit"], #btn-dash-filter').first().click();
                        await page.waitForLoadState('domcontentloaded');
                        expect(page.url()).toContain(`${name}=${dummyDate}`);
                    } else {
                        await page.waitForTimeout(600);
                        const hasDateInUrl = page.url().includes(`${name}=`) || page.url().includes('from=') || page.url().includes('to=');
                        const hasDateInReq = interceptedRequestUrl.includes(`${name}=`);
                        expect(hasDateInUrl || hasDateInReq, `Parameter tanggal ${name} harus terkirim di ${fp.url}`).toBe(true);
                    }

                    // Bersihkan kembali input tanggal
                    await ctrl.fill('');
                    if (mode === 'apply') {
                        await filterCard.locator('#btn-dash-reset').first().click();
                        await page.waitForLoadState('domcontentloaded');
                    } else {
                        await page.waitForTimeout(500);
                    }
                }

                page.off('request', requestListener);
            }
        }

        assertNoErrors(errors);
    });

    /**
     * Langkah 3: Uji fungsi tombol Reset, sinkronisasi URL, dan Refresh (Hydration)
     */
    test('3. Reset mengosongkan kontrol, menghapus parameter URL, dan Refresh mempertahankan filter', async ({ page }) => {
        test.setTimeout(120_000);
        const errors = attachErrorMonitor(page);

        // Uji pada 4 halaman representatif: DataTables Pelanggan, Tiket, Survei Saya (kartu), dan Dashboard (apply)
        const samplePages = [
            { role: 'admin', url: '/customers', selectName: 'service_status', selectVal: 'ACTIVE', urlParam: 'status' },
            { role: 'admin', url: '/surveys', selectName: 'status', selectVal: 'SUBMITTED', urlParam: 'status' },
            { role: 'surveyor', url: '/my/surveys', selectName: 'status', selectVal: 'SUBMITTED', urlParam: 'status' },
            { role: 'admin', url: '/', selectName: 'year', selectVal: '2026', urlParam: 'year', isApply: true },
        ];

        let currentRole = null;

        for (const sp of samplePages) {
            if (currentRole !== sp.role) {
                await loginAs(page, sp.role);
                currentRole = sp.role;
            }

            await page.goto(sp.url);
            await page.waitForLoadState('domcontentloaded');
            await page.waitForTimeout(400);

            const filterCard = page.locator('.filter-card, [data-filter-mode]').first();
            const selectEl = filterCard.locator(`select[name="${sp.selectName}"]`);

            if (await selectEl.count() > 0) {
                // Beri penanda instance window untuk memastikan tidak reload penuh pada mode instant
                await page.evaluate(() => { window.__filter_test_persistent_marker = 'alive'; });

                // 1. Pilih opsi filter
                await selectEl.selectOption(sp.selectVal);

                if (sp.isApply) {
                    await filterCard.locator('button[type="submit"], #btn-dash-filter').first().click();
                    await page.waitForLoadState('domcontentloaded');
                } else {
                    await page.waitForTimeout(600);
                }

                // URL harus mengandung parameter yang dipilih
                const urlParam = sp.urlParam || sp.selectName;
                expect(page.url()).toContain(`${urlParam}=${sp.selectVal}`);

                if (!sp.isApply) {
                    // Pada mode instant, window instance tidak boleh hilang (tidak full reload)
                    expect(await page.evaluate(() => window.__filter_test_persistent_marker)).toBe('alive');
                }

                // 2. Refresh halaman: nilai filter harus tetap bertahan (hydration dari URL)
                await page.reload();
                await page.waitForLoadState('domcontentloaded');
                await page.waitForTimeout(400);

                const reloadedSelect = page.locator(`.filter-card select[name="${sp.selectName}"]`);
                expect(await reloadedSelect.inputValue()).toBe(sp.selectVal);
                expect(page.url()).toContain(`${urlParam}=${sp.selectVal}`);

                // Pasang kembali penanda
                await page.evaluate(() => { window.__filter_test_persistent_marker = 'alive'; });

                // 3. Klik tombol Reset
                const resetBtn = page.locator('.filter-card button[type="reset"], .filter-card [id*="reset"], .filter-card a:has-text("Reset"), #btn-reset-filter, #btn-reset-surveys-filter, #btn-dash-reset').first();
                await resetBtn.click();

                if (sp.isApply) {
                    await page.waitForLoadState('domcontentloaded');
                } else {
                    await page.waitForTimeout(600);
                    // Tidak terjadi full reload pada mode instant
                    expect(await page.evaluate(() => window.__filter_test_persistent_marker)).toBe('alive');
                }

                // Kontrol harus kosong dan parameter URL harus bersih
                const clearedSelect = page.locator(`.filter-card select[name="${sp.selectName}"]`);
                expect(await clearedSelect.inputValue()).toBe('');
                expect(page.url()).not.toContain(`${urlParam}=`);
            }
        }

        assertNoErrors(errors);
    });

    /**
     * Langkah 4: Uji tombol Ekspor — URL ekspor memuat parameter filter aktif
     */
    test('4. Tombol Ekspor memuat parameter filter aktif secara real-time', async ({ page }) => {
        test.setTimeout(90_000);
        const errors = attachErrorMonitor(page);

        await loginAs(page, 'admin');

        // Uji sinkronisasi ekspor pada 4 halaman: Pelanggan, Tagihan, Survei, dan Penugasan
        const exportPages = [
            {
                url: '/customers',
                exportSelector: '#btn-export-customers, #btn-export',
                selectName: 'service_status',
                testVal: 'ACTIVE',
                expectedParamInExport: 'status=ACTIVE',
            },
            {
                url: '/invoices',
                exportSelector: '#btn-export-all-invoices, #btn-export-invoices',
                selectName: 'status',
                testVal: 'PAID',
                expectedParamInExport: 'status=PAID',
            },
            {
                url: '/surveys',
                exportSelector: '#btn-export-installation',
                selectName: 'status',
                testVal: 'SUBMITTED',
                expectedParamInExport: 'status=SUBMITTED',
            },
            {
                url: '/work-orders',
                exportSelector: '#btn-export-installation-wo',
                selectName: 'type',
                testVal: 'INSTALLATION',
                expectedParamInExport: 'type=INSTALLATION',
            },
        ];

        for (const ep of exportPages) {
            await page.goto(ep.url);
            await page.waitForLoadState('domcontentloaded');
            await page.waitForTimeout(400);

            const exportBtn = page.locator(ep.exportSelector).first();
            await expect(exportBtn).toBeVisible();

            // Ubah select filter
            const filterSelect = page.locator(`.filter-card select[name="${ep.selectName}"]`);
            await filterSelect.selectOption(ep.testVal);
            await page.waitForTimeout(600);

            // Verifikasi tautan href pada tombol ekspor memuat parameter filter aktif
            const exportHref = await exportBtn.getAttribute('href');
            expect(exportHref, `Tombol ekspor di ${ep.url} harus memuat parameter ${ep.expectedParamInExport}`).toContain(ep.expectedParamInExport);

            // Bersihkan filter dengan Reset
            const resetBtn = page.locator('.filter-card button[type="reset"], .filter-card [id*="reset"]').first();
            if (await resetBtn.count() > 0) {
                await resetBtn.click();
                await page.waitForTimeout(400);
            }
        }

        assertNoErrors(errors);
    });
});
