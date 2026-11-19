/**
 * 03-surveyor.spec.js — Fase 17g
 * Skenario E2E untuk role surveyor.
 */

import { test, expect } from '@playwright/test';
import { loginAs, attachErrorMonitor, assertNoErrors, waitForNetworkIdle } from './helpers.js';

test.describe('surveyor', () => {
    test.beforeEach(async ({ page }) => {
        await loginAs(page, 'surveyor');
    });

    test('(c1) Antrean Survei termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/survey-queue');
        await page.waitForLoadState('networkidle');
        // Halaman harus ada x-page-header / judul
        await expect(page.locator('h1.page-title').first()).toBeVisible({ timeout: 8_000 });
        assertNoErrors(errors);
    });

    test('(c2) Pencarian di Antrean Survei mengirim request (server-side filter)', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/survey-queue');
        await page.waitForLoadState('networkidle');

        const searchInput = page.locator('input[name="q"], input[name="search"]');
        await expect(searchInput).toBeVisible({ timeout: 5_000 });
        await searchInput.fill('KTU-0001');

        // Submit form (filter via GET / instant partial)
        await page.locator('button[type="submit"], button:has-text("Cari")').first().click();
        await page.waitForLoadState('networkidle');
        // URL harus mengandung q atau search param
        expect(page.url()).toMatch(/[?&](?:q|search)=/);
        assertNoErrors(errors);
    });

    test('(c3) Tombol Mulai Survei membuka offcanvas form survei', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/survey-queue');
        await page.waitForLoadState('networkidle');

        const btn = page.locator('.btn-start-survey').first();
        if (await btn.count() === 0) {
            // Tidak ada calon pelanggan — skip test ini
            test.skip();
            return;
        }
        await btn.click();
        // Offcanvas survei harus muncul
        await page.waitForSelector('#surveyOffcanvas.show, #surveyOffcanvas.showing', { timeout: 5_000 });
        assertNoErrors(errors);
    });

    test('(c4) Survei Saya termuat tanpa error', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/my/surveys');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('h1.page-title').first()).toBeVisible({ timeout: 8_000 });
        assertNoErrors(errors);
    });

    test('(c5) Filter status di Survei Saya berfungsi', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/my/surveys');
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

    test('(c6) Tombol Ubah Survei membuka offcanvas', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await page.goto('/my/surveys');
        await page.waitForLoadState('networkidle');

        const editBtn = page.locator('.btn-edit-survey').first();
        if (await editBtn.count() === 0) {
            test.skip(); // Tidak ada survei
            return;
        }
        await editBtn.click();
        await page.waitForSelector('.offcanvas.show, .offcanvas.showing', { timeout: 5_000 });
        assertNoErrors(errors);
    });
});

