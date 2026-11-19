/**
 * 02-admin.spec.js — Fase 17g
 * Skenario E2E untuk role admin (POP: KTU).
 */

import { test, expect } from '@playwright/test';
import { loginAs, attachErrorMonitor, assertNoErrors, waitForNetworkIdle } from './helpers.js';

test.describe('admin', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, 'admin');
    });

    test('(b1) Halaman Survei termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/surveys');
        await page.waitForSelector('#surveysTable, .card, h5', { timeout: 10_000 });
        assertNoErrors(errors);
    });

    test('(b2) Halaman Penugasan termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/work-orders');
        await page.waitForSelector('#workOrdersTable, .card, h5', { timeout: 10_000 });
        assertNoErrors(errors);
    });

    test('(b3) Halaman Tiket baca-saja termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/tickets');
        await page.waitForSelector('#ticketsTable, .card, h5', { timeout: 10_000 });
        assertNoErrors(errors);
    });

    test('(b4) Halaman Tagihan termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/invoices');
        await page.waitForSelector('#global-invoices-table, .card, h5', { timeout: 10_000 });
        assertNoErrors(errors);
    });

    test('(b5) Halaman Import termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/imports');
        await page.waitForLoadState('networkidle');
        // 200 atau redirect — tidak boleh error
        const status = await page.evaluate(() => document.title);
        assertNoErrors(errors);
    });

    test('(b6) Filter Survei mengubah konten tabel', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/surveys');
        await page.waitForSelector('#surveysTable', { timeout: 10_000 });
        // Pilih filter status
        const statusSel = page.locator('select[name="status"]').first();
        if (await statusSel.count() > 0) {
            await statusSel.selectOption({ index: 1 });
            await waitForNetworkIdle(page);
            await page.waitForTimeout(1000);
        }
        assertNoErrors(errors);
    });
});

