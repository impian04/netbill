/**
 * 08-filter-contract.spec.js — Fase 17k
 * Kontrak Pengujian Mekanisme Filter & Parameter URL pada 5 Halaman:
 * 1. Pelanggan (/customers)
 * 2. Tiket (/tickets)
 * 3. Survei Saya (/my/surveys)
 * 4. Dashboard (/)
 * 5. Cari Pelanggan (/customer-lookup)
 */

import { test, expect } from '@playwright/test';
import { loginAs, attachErrorMonitor, assertNoErrors, waitForNetworkIdle } from './helpers.js';

test.describe('Filter & URL Contract (Fase 17k)', () => {

    test('1. Pelanggan (/customers): filter instan, replaceState, refresh, reset, popstate, tanpa double request', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'super_admin');

        // a. Pemuatan awal dengan URL query berfilter: tepat 1 request ajax DataTables saat hydrate
        let ajaxCount = 0;
        page.on('request', (req) => {
            const url = req.url();
            if (url.includes('/customers') && req.headers()['x-requested-with'] === 'XMLHttpRequest') {
                ajaxCount++;
            }
        });

        await page.goto('/customers?status=ACTIVE');
        await page.waitForSelector('#customers-table tbody tr', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Verifikasi input select ter-hydrate
        const statusSelect = page.locator('select[name="status"], select[name="service_status"]').first();
        await expect(statusSelect).toHaveValue('ACTIVE');

        // Pemuatan awal hanya menghasilkan tepat 1 request ajax DataTables (bukan 2)
        expect(ajaxCount).toBe(1);

        // Pasang penanda di objek window untuk verifikasi bahwa halaman TIDAK mengalami full page reload
        await page.evaluate(() => { window.__test_page_instance = 'persistent'; });

        // b. Filter instan mengubah URL via replaceState tanpa full page reload
        await statusSelect.selectOption('SUSPENDED');
        await page.waitForTimeout(600); // tunggu debounce/change handler
        await waitForNetworkIdle(page);

        // URL berubah dan mengandung status=SUSPENDED
        expect(page.url()).toContain('status=SUSPENDED');
        // Instance window tidak boleh hilang (tidak ada full page reload)
        expect(await page.evaluate(() => window.__test_page_instance)).toBe('persistent');

        // c. Refresh halaman mempertahankan filter yang dipilih
        await page.reload();
        await page.waitForSelector('#customers-table tbody tr', { timeout: 10_000 });
        await expect(statusSelect).toHaveValue('SUSPENDED');
        expect(page.url()).toContain('status=SUSPENDED');

        // Pasang kembali penanda setelah reload
        await page.evaluate(() => { window.__test_page_instance = 'persistent'; });

        // d. Reset mengembalikan form ke kosong dan menghapus parameter dari URL tanpa reload halaman
        const resetBtn = page.locator('#filter-customers-form #btn-reset-filter, #btn-reset-filter, button:has-text("Reset")').first();
        await resetBtn.click();
        await page.waitForTimeout(600);
        await waitForNetworkIdle(page);

        expect(page.url()).not.toContain('status=');
        expect(page.url()).not.toContain('q=');
        await expect(statusSelect).toHaveValue('');
        // Tetap tidak terjadi full reload
        expect(await page.evaluate(() => window.__test_page_instance)).toBe('persistent');

        // e. Browser Back/Forward (popstate) memulihkan filter
        // Tambahkan entri histori untuk pengujian navigasi riwayat browser
        await page.evaluate(() => {
            history.pushState({}, '', '/customers?status=ACTIVE');
            window.dispatchEvent(new PopStateEvent('popstate'));
        });
        await page.waitForTimeout(300);
        await expect(statusSelect).toHaveValue('ACTIVE');

        await page.evaluate(() => {
            history.pushState({}, '', '/customers?status=SUSPENDED');
            window.dispatchEvent(new PopStateEvent('popstate'));
        });
        await page.waitForTimeout(300);
        await expect(statusSelect).toHaveValue('SUSPENDED');

        await page.goBack();
        await page.waitForTimeout(600);
        expect(page.url()).toContain('status=ACTIVE');
        await expect(statusSelect).toHaveValue('ACTIVE');

        await page.goForward();
        await page.waitForTimeout(600);
        expect(page.url()).toContain('status=SUSPENDED');
        await expect(statusSelect).toHaveValue('SUSPENDED');

        assertNoErrors(errors);
    });

    test('2. Tiket (/tickets): filter instan dan tombol reset', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'super_admin');

        await page.goto('/tickets');
        await page.waitForSelector('#ticketsTable tbody tr, #tickets-table tbody tr', { timeout: 10_000 });

        const statusSelect = page.locator('#filterTicketsForm select[name="status"]').first();
        if (await statusSelect.count() > 0) {
            await statusSelect.selectOption('OPEN');
            await page.waitForTimeout(600);
            await waitForNetworkIdle(page);

            expect(page.url()).toContain('status=OPEN');

            // Klik reset
            const resetBtn = page.locator('#filterTicketsForm button:has-text("Reset"), #filterTicketsForm a:has-text("Reset"), #btn-reset-filters').first();
            await resetBtn.click();
            await page.waitForTimeout(600);
            await waitForNetworkIdle(page);

            expect(page.url()).not.toContain('status=OPEN');
            await expect(statusSelect).toHaveValue('');
        }

        assertNoErrors(errors);
    });

    test('3. Survei Saya (/my/surveys): filter instan kartu via fetch partial', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'surveyor');

        await page.goto('/my/surveys');
        await page.waitForSelector('#surveysCardsWrapper', { timeout: 10_000 });

        await page.evaluate(() => { window.__test_page_instance = 'persistent'; });

        const statusSelect = page.locator('#filterSurveysForm select[name="status"]').first();
        if (await statusSelect.count() > 0) {
            await statusSelect.selectOption('SUBMITTED');
            await page.waitForTimeout(600);
            await waitForNetworkIdle(page);

            expect(page.url()).toContain('status=SUBMITTED');
            expect(await page.evaluate(() => window.__test_page_instance)).toBe('persistent');

            // Reset
            const resetBtn = page.locator('#filterSurveysForm button:has-text("Reset"), #filterSurveysForm a:has-text("Reset"), #btn-reset-surveys').first();
            await resetBtn.click();
            await page.waitForTimeout(600);
            await waitForNetworkIdle(page);

            expect(page.url()).not.toContain('status=SUBMITTED');
            expect(await page.evaluate(() => window.__test_page_instance)).toBe('persistent');
        }

        assertNoErrors(errors);
    });

    test('4. Dashboard (/): mode apply via submit GET biasa dan reset', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'super_admin');

        await page.goto('/');
        await page.waitForSelector('#dashboard-filter-form', { timeout: 10_000 });

        const popSelect = page.locator('#dashboard-filter-form select[name="pop_id"]').first();
        await expect(popSelect).toBeVisible();

        await popSelect.selectOption({ index: 1 });

        // Klik tombol Filter (submit form biasa)
        const filterBtn = page.locator('#dashboard-filter-form button[type="submit"]:has-text("Filter")').first();
        await filterBtn.click();
        await page.waitForLoadState('networkidle');

        // URL mengandung ?pop_id=...
        expect(page.url()).toContain('pop_id=');

        // Klik Reset (link kembali ke dashboard)
        const resetBtn = page.locator('#dashboard-filter-form a:has-text("Reset")').first();
        await resetBtn.click();
        await page.waitForLoadState('networkidle');

        expect(page.url()).not.toContain('pop_id=');

        assertNoErrors(errors);
    });

    test('5. Cari Pelanggan (/customer-lookup): input < 3 karakter tidak request, submit Enter/Cari memicu request', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'noc');

        await page.goto('/customer-lookup');
        await page.waitForSelector('#nocCustomersTable', { timeout: 10_000 });

        let searchRequestCount = 0;
        page.on('request', (req) => {
            if (req.url().includes('/customer-lookup') && req.headers()['x-requested-with'] === 'XMLHttpRequest') {
                searchRequestCount++;
            }
        });

        const searchInput = page.locator('#filterNocCustomersForm input[name="q"]').first();
        await expect(searchInput).toBeVisible();

        // Ketik 2 karakter: tidak boleh memicu AJAX search
        searchRequestCount = 0;
        await searchInput.fill('KT');
        await page.waitForTimeout(600);
        expect(searchRequestCount).toBe(0);

        // Ketik minimal 3 karakter lalu klik tombol Cari
        await searchInput.fill('KTU');
        const cariBtn = page.locator('#btn-search-noc, #filterNocCustomersForm button[type="submit"]:has-text("Cari")').first();
        await cariBtn.click();
        await waitForNetworkIdle(page);

        // Harus ada request yang dikirim dan URL terupdate
        expect(searchRequestCount).toBeGreaterThan(0);
        expect(page.url()).toMatch(/[?&]q=KTU/);

        assertNoErrors(errors);
    });

});
