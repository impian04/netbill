/**
 * 04-technician.spec.js — Fase 17g
 * Skenario E2E untuk role technician.
 */

import { test, expect } from '@playwright/test';
import { loginAs, attachErrorMonitor, assertNoErrors, waitForNetworkIdle } from './helpers.js';

test.describe('technician', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, 'technician');
    });

    test('(d1) Tugas Saya termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/my/tasks');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('h1.page-title').first()).toBeVisible({ timeout: 8_000 });
        assertNoErrors(errors);
    });

    test('(d2) Filter status di Tugas Saya berfungsi', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/my/tasks');
        await page.waitForLoadState('networkidle');

        const statusSel = page.locator('select[name="status"]');
        if (await statusSel.count() > 0) {
            await statusSel.selectOption({ index: 1 });
            await page.locator('button[type="submit"], button:has-text("Cari")').first().click();
            await page.waitForLoadState('networkidle');
            expect(page.url()).toContain('status=');
        }
        assertNoErrors(errors);
    });

    test('(d3) Pencarian di Tugas Saya berfungsi', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/my/tasks');
        await page.waitForLoadState('networkidle');

        const searchInput = page.locator('input[name="q"], input[name="search"]');
        if (await searchInput.count() > 0) {
            await searchInput.fill('KTU');
            await page.locator('button[type="submit"], button:has-text("Cari")').first().click();
            await page.waitForLoadState('networkidle');
            expect(page.url()).toMatch(/[?&](?:q|search)=/);
        }
        assertNoErrors(errors);
    });

    test('(d4) Tombol Kerjakan/Foto (btn-open-task) membuka offcanvas detail', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/my/tasks');
        await page.waitForLoadState('networkidle');

        const openBtn = page.locator('.btn-open-task').first();
        if (await openBtn.count() === 0) {
            test.skip(); // Tidak ada tugas
            return;
        }
        await openBtn.click();
        await page.waitForSelector('.offcanvas.show, .offcanvas.showing', { timeout: 8_000 });
        assertNoErrors(errors);
    });

    test('(d5) Tidak ada data KTP, rekening, atau aktivasi di Tugas Saya', async ({ page }) => {
        await page.goto('/my/tasks');
        await page.waitForLoadState('networkidle');
        const html = await page.content();
        // Pastikan data sensitif tidak bocor ke tampilan teknisi
        expect(html).not.toMatch(/ktp_path|no_rekening|activation_code/i);
    });
});

