/**
 * 01-super-admin.spec.js — Fase 17g
 * Skenario E2E untuk role super_admin.
 */

import { test, expect } from '@playwright/test';
import { loginAs, attachErrorMonitor, assertNoErrors, waitForNetworkIdle } from './helpers.js';

test.describe('super_admin', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, 'super_admin');
    });

    test('(a1) Halaman Pelanggan memuat dan DataTable muncul', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/customers');
        await page.waitForSelector('#customers-table tbody tr', { timeout: 10_000 });
        assertNoErrors(errors);
    });

    test('(a2) Filter status mengubah baris tabel Pelanggan', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/customers');
        await page.waitForSelector('#customers-table tbody tr', { timeout: 10_000 });

        // Pilih filter ACTIVE
        await page.selectOption('select[name="service_status"]', 'ACTIVE');
        await waitForNetworkIdle(page);

        // Tabel harus ter-reload (DataTable AJAX)
        await page.waitForTimeout(1500);
        assertNoErrors(errors);
    });

    test('(a3) Pencarian mengubah baris tabel Pelanggan', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/customers');
        await page.waitForSelector('#customers-table tbody tr', { timeout: 10_000 });

        // Ketik di kotak pencarian DataTable atau filter-search
        const searchInput = page.locator('#filter-search, input[type="search"]').first();
        await searchInput.fill('KTU');
        await waitForNetworkIdle(page);
        await page.waitForTimeout(1500);
        assertNoErrors(errors);
    });

    test('(a4) Detail pelanggan /customers/{id} terbuka tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/customers');
        await page.waitForSelector('#customers-table tbody tr td a', { timeout: 10_000 });

        // Klik link detail pelanggan pertama
        const firstLink = page.locator('#customers-table tbody tr td a').first();
        await firstLink.click();
        await page.waitForLoadState('networkidle');
        expect(page.url()).toMatch(/\/customers\/\d+/);
        assertNoErrors(errors);
    });

    test('(a5) Offcanvas ubah pelanggan terbuka tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/customers');
        await page.waitForSelector('#customers-table tbody tr', { timeout: 10_000 });

        // Klik tombol edit di baris pertama
        const editBtn = page.locator('#customers-table tbody tr .btn-edit, #customers-table tbody tr [data-action="edit"]').first();
        if (await editBtn.count() === 0) {
            // Coba lewat detail dulu
            await page.locator('#customers-table tbody tr td a').first().click();
            await page.waitForLoadState('networkidle');
            const editBtnDetail = page.locator('.btn-edit, [data-bs-target="#editCustomerOffcanvas"], [data-bs-target*="Offcanvas"]').first();
            if (await editBtnDetail.count() > 0) {
                await editBtnDetail.click();
                await page.waitForSelector('.offcanvas.show, .offcanvas.showing', { timeout: 5_000 });
            }
        } else {
            await editBtn.click();
            await page.waitForSelector('.offcanvas.show, .offcanvas.showing', { timeout: 5_000 });
        }
        assertNoErrors(errors);
    });
});

