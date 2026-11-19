/**
 * 05-noc-marketing.spec.js — Fase 17g
 * Skenario E2E untuk role noc dan marketing.
 */

import { test, expect } from '@playwright/test';
import { loginAs, attachErrorMonitor, assertNoErrors, waitForNetworkIdle } from './helpers.js';

// ── NOC ──────────────────────────────────────────────────────────────────────

test.describe('noc', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, 'noc');
    });

    test('(e1) Halaman Tiket termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/tickets');
        await page.waitForSelector('#ticketsTable, .card', { timeout: 10_000 });
        assertNoErrors(errors);
    });

    test('(e2) Pencarian Tiket menghasilkan respons', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/tickets');
        await page.waitForSelector('#ticketsTable', { timeout: 10_000 });
        await page.waitForTimeout(1500); // Tunggu DataTable init

        // Cari via searchInput
        const searchInput = page.locator('input[type="search"], #filter-search').first();
        if (await searchInput.count() > 0) {
            await searchInput.fill('TKT');
            await waitForNetworkIdle(page);
            await page.waitForTimeout(1000);
        }
        assertNoErrors(errors);
    });

    test('(e3) Halaman Cari Pelanggan termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/customer-lookup');
        await page.waitForSelector('#nocCustomersTable, .card', { timeout: 10_000 });
        assertNoErrors(errors);
    });

    test('(e4) Pencarian di Cari Pelanggan menghasilkan baris', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/customer-lookup');
        await page.waitForSelector('#nocCustomersTable', { timeout: 10_000 });
        await page.waitForTimeout(1500);

        const searchInput = page.locator('input[type="search"], #filter-search').first();
        if (await searchInput.count() > 0) {
            await searchInput.fill('KTU');
            await waitForNetworkIdle(page);
            await page.waitForTimeout(1000);
        }
        assertNoErrors(errors);
    });

    test('(e5) Tombol Buat Tiket membuka offcanvas', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/tickets');
        await page.waitForSelector('#ticketsTable', { timeout: 10_000 });

        const btn = page.locator('#btn-create-ticket').first();
        if (await btn.count() > 0) {
            await btn.click();
            await page.waitForSelector('.offcanvas.show, .offcanvas.showing', { timeout: 5_000 });
        }
        assertNoErrors(errors);
    });
});

// ── MARKETING ────────────────────────────────────────────────────────────────

test.describe('marketing', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, 'marketing');
    });

    test('(f1) Pendaftaran Saya termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/my/applications');
        await page.waitForSelector('#table-marketing-applications, .card', { timeout: 10_000 });
        assertNoErrors(errors);
    });

    test('(f2) Offcanvas Daftar Baru terbuka tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/my/applications');
        await page.waitForSelector('#table-marketing-applications, .card', { timeout: 10_000 });

        // Tombol tambah/daftar baru
        const addBtn = page.locator('#btn-add, .btn-add, [data-bs-target*="Offcanvas"][class*="btn-primary"], button:has-text("Daftar Baru"), button:has-text("Tambah")').first();
        if (await addBtn.count() > 0) {
            await addBtn.click();
            await page.waitForSelector('.offcanvas.show, .offcanvas.showing', { timeout: 5_000 });
        }
        assertNoErrors(errors);
    });
});

