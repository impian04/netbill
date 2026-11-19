/**
 * 06-ui-contract.spec.js — Fase 17h
 * Uji E2E Playwright untuk kontrak gaya dan token desain UI NetBill.
 * Database: db_netbill_e2e
 */

import { test, expect } from '@playwright/test';
import { loginAs, attachErrorMonitor, assertNoErrors, waitForNetworkIdle } from './helpers.js';
import path from 'path';

const VIEWPORTS = [
    { name: 'desktop', width: 1280, height: 800, expectedPadding: '24px' },
    { name: 'mobile',  width: 360,  height: 740, expectedPadding: '12px' },
];

const PAGES = [
    { name: 'customers', url: '/customers', role: 'super_admin' },
    { name: 'surveys',   url: '/surveys',   role: 'admin' },
    { name: 'my-tasks',  url: '/my/tasks',  role: 'technician' },
];

test.describe('Kontrak Gaya Desain UI NetBill', () => {
    for (const vp of VIEWPORTS) {
        for (const pg of PAGES) {
            test(`[${vp.name}] ${pg.name} (${pg.url}) mematuhi token desain`, async ({ page }) => {
                await page.setViewportSize({ width: vp.width, height: vp.height });
                const errors = attachErrorMonitor(page);

                await loginAs(page, pg.role);
                await page.goto(pg.url);
                await waitForNetworkIdle(page);
                await page.waitForTimeout(1000);

                assertNoErrors(errors);

                // (a) Computed style padding #main-content
                const mainPadding = await page.evaluate(() => {
                    const el = document.querySelector('#main-content');
                    if (!el) return null;
                    const style = window.getComputedStyle(el);
                    return {
                        left: style.paddingLeft,
                        right: style.paddingRight,
                    };
                });
                expect(mainPadding).not.toBeNull();
                expect(mainPadding.left).toBe(vp.expectedPadding);
                expect(mainPadding.right).toBe(vp.expectedPadding);

                // (b) Bounding box jarak vertikal dari .page-header ke elemen bagian berikutnya
                const gap = await page.evaluate(() => {
                    const header = document.querySelector('.page-header');
                    if (!header || !header.nextElementSibling) return null;
                    const nextEl = header.nextElementSibling;
                    const hRect = header.getBoundingClientRect();
                    const nRect = nextEl.getBoundingClientRect();
                    return Math.round(nRect.top - hRect.bottom);
                });
                if (gap !== null) {
                    // Margin-bottom 1.5rem = 24px (toleransi ±2px untuk subpixel)
                    expect(gap).toBeGreaterThanOrEqual(22);
                    expect(gap).toBeLessThanOrEqual(26);
                }

                // (c) Tinggi kontrol input -sm dan select -sm konsisten 31px (±1px)
                const controlHeights = await page.evaluate(() => {
                    const controls = document.querySelectorAll('input.form-control-sm:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select.form-select-sm');
                    return Array.from(controls)
                        .filter(c => c.offsetParent !== null)
                        .map(c => Math.round(c.getBoundingClientRect().height));
                });
                for (const h of controlHeights) {
                    expect(h).toBeGreaterThanOrEqual(30);
                    expect(h).toBeLessThanOrEqual(32);
                }

                // (d) Warna computed background tombol dan badge (token Bootstrap/NetBill)
                const colors = await page.evaluate(() => {
                    const primaryBtn = document.querySelector('.btn-primary');
                    const dangerBtn  = document.querySelector('.btn-danger');
                    const successBadge = document.querySelector('.badge.bg-success');
                    const warningBadge = document.querySelector('.badge.bg-warning');

                    return {
                        primaryBtn: primaryBtn ? window.getComputedStyle(primaryBtn).backgroundColor : null,
                        dangerBtn: dangerBtn ? window.getComputedStyle(dangerBtn).backgroundColor : null,
                        successBadge: successBadge ? window.getComputedStyle(successBadge).backgroundColor : null,
                        warningBadge: warningBadge ? window.getComputedStyle(warningBadge).backgroundColor : null,
                    };
                });

                // primary = #0d6efd (rgb(13, 110, 253))
                if (colors.primaryBtn) {
                    expect(colors.primaryBtn).toBe('rgb(13, 110, 253)');
                }
                // danger = #dc3545 (rgb(220, 53, 69))
                if (colors.dangerBtn) {
                    expect(colors.dangerBtn).toBe('rgb(220, 53, 69)');
                }
                // success = #198754 (rgb(25, 135, 84))
                if (colors.successBadge) {
                    expect(colors.successBadge).toBe('rgb(25, 135, 84)');
                }
                // warning = #ffc107 (rgb(255, 193, 7))
                if (colors.warningBadge) {
                    expect(colors.warningBadge).toBe('rgb(255, 193, 7)');
                }

                // Simpan screenshot untuk dokumentasi visual
                const screenshotPath = path.join('e2e', 'screenshots', `${pg.name}-${vp.name}.png`);
                await page.screenshot({ path: screenshotPath, fullPage: false });
            });
        }
    }
});

