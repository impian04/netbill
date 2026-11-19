/**
 * 09-typography-contract.spec.js — Fase 17l
 * Kontrak Pengujian Tipografi Baku Seluruh Aplikasi NetBill:
 * 1. Menangkap screenshot "after" (1280px & 360px) ke e2e/screenshots/after
 * 2. Memastikan tidak ada teks tampil di bawah 12px (0.75rem, toleransi 0.5px)
 * 3. Memastikan maksimal 7 ukuran font berbeda per halaman
 * 4. Mengukur kepatuhan komponen terhadap token tipografi baku (toleransi 0.5px)
 */

import { test, expect } from '@playwright/test';
import { loginAs, attachErrorMonitor, assertNoErrors, waitForNetworkIdle } from './helpers.js';
import path from 'path';

const AUDIT_PAGES = [
    { role: 'super_admin', path: '/',            name: 'dashboard' },
    { role: 'super_admin', path: '/customers',    name: 'customers' },
    { role: 'super_admin', path: '/invoices',     name: 'invoices' },
    { role: 'super_admin', path: '/pops',         name: 'pops' },
    { role: 'super_admin', path: '/users',        name: 'users' },
    { role: 'super_admin', path: '/announcements',name: 'announcements' },
    { role: 'admin',       path: '/surveys',      name: 'surveys' },
    { role: 'admin',       path: '/work-orders',  name: 'work-orders' },
    { role: 'surveyor',    path: '/survey-queue', name: 'survey-queue' },
    { role: 'surveyor',    path: '/my/surveys',   name: 'my-surveys' },
    { role: 'technician',  path: '/my/tasks',     name: 'my-tasks' },
    { role: 'noc',         path: '/customer-lookup', name: 'customer-lookup' },
    { role: 'noc',         path: '/tickets',      name: 'tickets' },
    { role: 'marketing',   path: '/applications', name: 'applications' },
];

test.describe('Typography Contract (Fase 17l)', () => {

    test('1. [Desktop 1280px] Audit ukuran font, batas minimum >= 12px, maks 7 ukuran, dan capture screenshot after', async ({ page }) => {
        test.setTimeout(120_000);
        await page.setViewportSize({ width: 1280, height: 800 });

        let currentRole = null;
        for (const item of AUDIT_PAGES) {
            if (currentRole !== item.role) {
                await page.context().clearCookies();
                await loginAs(page, item.role);
                currentRole = item.role;
            }

            await page.goto(item.path);
            await waitForNetworkIdle(page);
            await page.waitForTimeout(400);

            // Simpan screenshot after
            const screenshotName = `${item.role}_${item.name}_1280px.png`;
            const screenshotPath = path.join(process.cwd(), 'e2e', 'screenshots', 'after', screenshotName);
            await page.screenshot({ path: screenshotPath, fullPage: true });

            // Audit tipografi elemen teks yang tampil
            const typoAudit = await page.evaluate(() => {
                const textElements = Array.from(document.querySelectorAll('body *:not(script):not(style):not(svg):not(path)'))
                    .filter(el => {
                        if (!el.offsetParent && el.tagName !== 'BODY') return false;
                        const text = el.textContent ? el.textContent.trim() : '';
                        if (!text) return false;
                        // Hanya cek elemen yang memiliki text node langsung agar tidak dobel hitung container
                        return Array.from(el.childNodes).some(n => n.nodeType === Node.TEXT_NODE && n.nodeValue.trim().length > 0);
                    });

                const fontSizes = new Set();
                const belowMin = [];

                textElements.forEach(el => {
                    const cs = window.getComputedStyle(el);
                    const fsPx = parseFloat(cs.fontSize);
                    if (isNaN(fsPx)) return;

                    fontSizes.add(Math.round(fsPx * 10) / 10);

                    // Batas bawah 12px (toleransi 0.5px -> 11.5px)
                    if (fsPx < 11.5) {
                        belowMin.push({
                            tag: el.tagName,
                            class: el.className,
                            text: el.textContent.trim().substring(0, 30),
                            fontSize: cs.fontSize,
                        });
                    }
                });

                return {
                    uniqueSizesCount: fontSizes.size,
                    sizes: Array.from(fontSizes).sort((a, b) => a - b),
                    belowMin,
                };
            });

            // Gagal jika ada teks tampil di bawah 12px
            expect(
                typoAudit.belowMin,
                `Halaman [${item.name}] memiliki teks di bawah 12px: ${JSON.stringify(typoAudit.belowMin)}`
            ).toHaveLength(0);

            // Gagal jika sebuah halaman punya lebih dari 7 ukuran font berbeda
            expect(
                typoAudit.uniqueSizesCount,
                `Halaman [${item.name}] memiliki ${typoAudit.uniqueSizesCount} ukuran font (maksimal 7): ${typoAudit.sizes.join(', ')}`
            ).toBeLessThanOrEqual(7);
        }
    });

    test('2. [Mobile 360px] Audit ukuran font, batas minimum >= 12px, maks 7 ukuran, dan capture screenshot after', async ({ page }) => {
        test.setTimeout(120_000);
        await page.setViewportSize({ width: 360, height: 640 });

        let currentRole = null;
        for (const item of AUDIT_PAGES) {
            if (currentRole !== item.role) {
                await page.context().clearCookies();
                await loginAs(page, item.role);
                currentRole = item.role;
            }

            await page.goto(item.path);
            await waitForNetworkIdle(page);
            await page.waitForTimeout(400);

            // Simpan screenshot after
            const screenshotName = `${item.role}_${item.name}_360px.png`;
            const screenshotPath = path.join(process.cwd(), 'e2e', 'screenshots', 'after', screenshotName);
            await page.screenshot({ path: screenshotPath, fullPage: true });

            // Audit tipografi elemen teks yang tampil
            const typoAudit = await page.evaluate(() => {
                const textElements = Array.from(document.querySelectorAll('body *:not(script):not(style):not(svg):not(path)'))
                    .filter(el => {
                        if (!el.offsetParent && el.tagName !== 'BODY') return false;
                        const text = el.textContent ? el.textContent.trim() : '';
                        if (!text) return false;
                        return Array.from(el.childNodes).some(n => n.nodeType === Node.TEXT_NODE && n.nodeValue.trim().length > 0);
                    });

                const fontSizes = new Set();
                const belowMin = [];

                textElements.forEach(el => {
                    const cs = window.getComputedStyle(el);
                    const fsPx = parseFloat(cs.fontSize);
                    if (isNaN(fsPx)) return;

                    fontSizes.add(Math.round(fsPx * 10) / 10);

                    if (fsPx < 11.5) {
                        belowMin.push({
                            tag: el.tagName,
                            class: el.className,
                            text: el.textContent.trim().substring(0, 30),
                            fontSize: cs.fontSize,
                        });
                    }
                });

                return {
                    uniqueSizesCount: fontSizes.size,
                    sizes: Array.from(fontSizes).sort((a, b) => a - b),
                    belowMin,
                };
            });

            expect(
                typoAudit.belowMin,
                `Halaman Mobile [${item.name}] memiliki teks di bawah 12px: ${JSON.stringify(typoAudit.belowMin)}`
            ).toHaveLength(0);

            expect(
                typoAudit.uniqueSizesCount,
                `Halaman Mobile [${item.name}] memiliki ${typoAudit.uniqueSizesCount} ukuran font (maksimal 7): ${typoAudit.sizes.join(', ')}`
            ).toBeLessThanOrEqual(7);
        }
    });

    test('3. Ukur peran komponen terhadap token tipografi baku (toleransi 0.5px)', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.context().clearCookies();
        await loginAs(page, 'super_admin');

        // Buka Customers
        await page.goto('/customers');
        await page.waitForSelector('#customers-table tbody tr', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Helper ukur computed font-size dalam px
        const getFontSizePx = async (selector) => {
            return await page.evaluate((sel) => {
                const el = document.querySelector(sel);
                if (!el) return null;
                return parseFloat(window.getComputedStyle(el).fontSize);
            }, selector);
        };

        // 1. Judul Halaman: --nb-font-xl (1.25rem = 20px)
        const pageTitleSize = await getFontSizePx('.page-title');
        expect(pageTitleSize).toBeCloseTo(20, 0); // 19.5 - 20.5

        // 2. Subjudul Halaman: --nb-font-sm (0.8125rem = 13px)
        const pageSubtitleSize = await getFontSizePx('.page-subtitle');
        expect(pageSubtitleSize).toBeCloseTo(13, 0); // 12.5 - 13.5

        // 3. Header Tabel: --nb-font-sm (0.8125rem = 13px)
        const tableThSize = await getFontSizePx('#customers-table th');
        expect(tableThSize).toBeCloseTo(13, 0);

        // 4. Isi Tabel: --nb-font-base (0.875rem = 14px)
        const tableTdSize = await getFontSizePx('#customers-table tbody td');
        expect(tableTdSize).toBeCloseTo(14, 0);

        // 5. Label Form: --nb-font-base (0.875rem = 14px)
        const formLabelSize = await getFontSizePx('.form-label');
        expect(formLabelSize).toBeCloseTo(14, 0);

        // 6. Kontrol Form: --nb-font-base (0.875rem = 14px)
        const formControlSize = await getFontSizePx('.form-control-sm');
        expect(formControlSize).toBeCloseTo(14, 0);

        // 7. Tombol SM: --nb-font-base (0.875rem = 14px)
        const btnSmSize = await getFontSizePx('.btn-sm');
        expect(btnSmSize).toBeCloseTo(14, 0);

        // 8. Badge Status: --nb-font-xs (0.75rem = 12px)
        const badgeSize = await getFontSizePx('.badge');
        expect(badgeSize).toBeCloseTo(12, 0);

        // 9. Buka Offcanvas Pelanggan
        await page.locator('#btn-open-create-customer').click();
        await page.waitForSelector('#offcanvas-customer.show', { timeout: 5_000 });

        const offcanvasTitleSize = await getFontSizePx('#offcanvas-customer .offcanvas-title');
        expect(offcanvasTitleSize).toBeCloseTo(18, 0); // --nb-font-lg = 1.125rem = 18px

        // 10. Dashboard: Stat Value (24px) & Stat Label (13px)
        await page.goto('/');
        await page.waitForSelector('#dashboard-filter-form', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        const statValueSize = await getFontSizePx('.stat-value');
        expect(statValueSize).toBeCloseTo(24, 0); // --nb-font-2xl = 1.5rem = 24px

        const statLabelSize = await getFontSizePx('.stat-label');
        expect(statLabelSize).toBeCloseTo(13, 0); // --nb-font-sm = 13px

        // 11. Modal SweetAlert & Toast
        const modalTypo = await page.evaluate(async () => {
            if (typeof window.confirmDialog === 'function') {
                window.confirmDialog({ title: 'Uji Tipografi Modal' });
            }
            const titleEl = document.querySelector('.swal2-title');
            const bodyEl = document.querySelector('.swal2-html-container');
            const btnEl = document.querySelector('.swal2-confirm');
            return {
                title: titleEl ? parseFloat(getComputedStyle(titleEl).fontSize) : null,
                body: bodyEl ? parseFloat(getComputedStyle(bodyEl).fontSize) : null,
                button: btnEl ? parseFloat(getComputedStyle(btnEl).fontSize) : null,
            };
        });
        expect(modalTypo.title).toBeCloseTo(18, 0); // 18px (Fase 17m: lg, lebih kecil dari judul halaman 20px)
        expect(modalTypo.button).toBeCloseTo(14, 0); // 14px

        // Toast
        const toastTypo = await page.evaluate(() => {
            if (typeof window.toast === 'function') {
                window.toast('info', 'Uji Toast Tipografi');
            }
            const el = document.querySelector('.swal2-toast .swal2-title, .toast-body');
            return el ? parseFloat(getComputedStyle(el).fontSize) : null;
        });
        expect(toastTypo).toBeCloseTo(14, 0); // 14px
    });

});

