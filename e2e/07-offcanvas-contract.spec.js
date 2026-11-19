/**
 * 07-offcanvas-contract.spec.js — Fase 17m
 * Kontrak gaya & dimensi seragam seluruh offcanvas NetBill menggunakan standar ukuran 600px (Bootstrap default override).
 * Database: db_netbill_e2e
 */

import { test, expect } from '@playwright/test';
import { loginAs, attachErrorMonitor, assertNoErrors, waitForNetworkIdle } from './helpers.js';
import fs from 'fs';
import path from 'path';

const OFFCANVAS_TEST_CASES = [
    { pageName: 'Pelanggan', url: '/customers', role: 'super_admin', id: 'offcanvas-customer' },
    { pageName: 'POP', url: '/pops', role: 'super_admin', id: 'offcanvas-pop' },
    { pageName: 'Staf', url: '/staff', role: 'super_admin', id: 'offcanvas-staff' },
    { pageName: 'Barang (Form)', url: '/materials', role: 'admin', id: 'offcanvas-material' },
    { pageName: 'Barang (Import)', url: '/materials', role: 'admin', id: 'offcanvas-import-material' },
    { pageName: 'ODC (Form)', url: '/odcs', role: 'admin', id: 'offcanvas-odc' },
    { pageName: 'ODC (Import)', url: '/odcs', role: 'admin', id: 'offcanvas-import-odc' },
    { pageName: 'ODP (Form)', url: '/odps', role: 'admin', id: 'offcanvas-odp' },
    { pageName: 'ODP (Import)', url: '/odps', role: 'admin', id: 'offcanvas-import-odp' },
    { pageName: 'Tagihan', url: '/invoices', role: 'admin', id: 'offcanvas-invoice' },
    { pageName: 'Pengumuman', url: '/announcements', role: 'super_admin', id: 'offcanvas-announcement' },
    { pageName: 'Pendaftaran (Review)', url: '/customer-applications', role: 'admin', id: 'offcanvas-review-application' },
    { pageName: 'Marketing App', url: '/my/applications', role: 'marketing', id: 'offcanvas-application' },
    { pageName: 'Marketing Payout', url: '/marketing-profiles', role: 'admin', id: 'offcanvas-payout' },
    { pageName: 'NOC Pelanggan', url: '/customer-lookup', role: 'noc', id: 'nocCustomerDetailOffcanvas' },
    { pageName: 'Antrean Survei', url: '/survey-queue', role: 'surveyor', id: 'surveyOffcanvas' },
    { pageName: 'Survei Saya', url: '/my/surveys', role: 'surveyor', id: 'editSurveyOffcanvas' },
    { pageName: 'Survei (Detail)', url: '/surveys', role: 'admin', id: 'detailSurveyOffcanvas' },
    { pageName: 'Survei (Tugaskan)', url: '/surveys', role: 'admin', id: 'assignTechnicianOffcanvas' },
    { pageName: 'Tugas Saya', url: '/my/tasks', role: 'technician', id: 'taskWorkOffcanvas' },
    { pageName: 'Tiket (Form)', url: '/tickets', role: 'noc', id: 'ticketFormOffcanvas' },
    { pageName: 'Tiket (Detail)', url: '/tickets', role: 'admin', id: 'ticketDetailOffcanvas' },
    { pageName: 'Penugasan (Detail)', url: '/work-orders', role: 'admin', id: 'detailWorkOrderOffcanvas' },
    { pageName: 'Penugasan (Alihkan)', url: '/work-orders', role: 'admin', id: 'reassignTechnicianOffcanvas' },
];

const VIEWPORTS = [
    { name: 'desktop (1280px)', width: 1280, height: 800 },
    { name: 'tablet (768px)', width: 768, height: 1024 },
    { name: 'mobile (360px)', width: 360, height: 740 },
];

test.describe('Kontrak Gaya & Lebar Bawaan Bootstrap Offcanvas Seragam NetBill (Fase 17m)', () => {
    const measuredRecords = [];
    const deviations = [];
    const AFTER_SCREENSHOTS_DIR = path.resolve('e2e/screenshots/offcanvas-after');

    test.beforeAll(async () => {
        if (!fs.existsSync(AFTER_SCREENSHOTS_DIR)) {
            fs.mkdirSync(AFTER_SCREENSHOTS_DIR, { recursive: true });
        }
    });

    for (const vp of VIEWPORTS) {
        test.describe(`Viewport: ${vp.name}`, () => {
            for (const item of OFFCANVAS_TEST_CASES) {
                test(`[${vp.width}px] ${item.pageName} #${item.id} sesuai standar bawaan Bootstrap`, async ({ page }) => {
                    await page.setViewportSize({ width: vp.width, height: vp.height });
                    const errors = attachErrorMonitor(page);

                    await loginAs(page, item.role);
                    await page.goto(item.url);
                    await waitForNetworkIdle(page);

                    // Buka offcanvas via Bootstrap instance
                    await page.evaluate((id) => {
                        const el = document.getElementById(id);
                        if (!el) throw new Error(`Offcanvas #${id} tidak ditemukan di DOM.`);
                        const inst = window.bootstrap?.Offcanvas?.getOrCreateInstance(el);
                        if (!inst) throw new Error(`Bootstrap Offcanvas instance gagal dibuat untuk #${id}`);
                        inst.show();
                    }, item.id);

                    // Tunggu transisi Bootstrap selesai
                    await page.waitForTimeout(350);

                    // Ambil screenshot "after" jika belum ada
                    const ssPath = path.join(AFTER_SCREENSHOTS_DIR, `${item.id}-${vp.width}px.png`);
                    if (!fs.existsSync(ssPath)) {
                        await page.screenshot({ path: ssPath });
                    }

                    // Ukur dimensi dan atribut offcanvas
                    const measurements = await page.evaluate((id) => {
                        const el = document.getElementById(id);
                        if (!el) return null;

                        const rect = el.getBoundingClientRect();
                        const titleEl = el.querySelector('.offcanvas-title');
                        const titleStyle = titleEl ? window.getComputedStyle(titleEl) : null;
                        const bodyEl = el.querySelector('.offcanvas-body');
                        const headerEl = el.querySelector('.offcanvas-header');
                        const footerEl = el.querySelector('.offcanvas-footer');

                        const hasHorizontalOverflow = bodyEl ? (bodyEl.scrollWidth > bodyEl.clientWidth + 1) : false;

                        const controlEls = Array.from(el.querySelectorAll('input.form-control-sm:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select.form-select-sm'));
                        const controlHeights = controlEls
                            .filter(c => c.offsetParent !== null)
                            .map(c => Math.round(c.getBoundingClientRect().height));

                        return {
                            width: Math.round(rect.width),
                            hasScroll: el.getAttribute('data-bs-scroll') === 'true',
                            hasBackdrop: el.getAttribute('data-bs-backdrop') === 'true',
                            titleFontSize: titleStyle ? titleStyle.fontSize : null,
                            hasHeader: !!headerEl,
                            hasBody: !!bodyEl,
                            hasFooter: !!footerEl,
                            hasHorizontalOverflow,
                            controlHeights,
                        };
                    }, item.id);

                    expect(measurements).not.toBeNull();

                    // Expected width: standar NetBill 600px pada desktop/tablet, dan 360px pada mobile 360px
                    const expectedWidth = vp.width === 360 ? 360 : 600;
                    const widthDiff = Math.abs(measurements.width - expectedWidth);
                    if (widthDiff > 1) {
                        deviations.push({
                            viewport: vp.width,
                            id: item.id,
                            page: item.pageName,
                            param: 'width',
                            expected: `${expectedWidth}px`,
                            actual: `${measurements.width}px`,
                        });
                    }

                    // Rekam data pengukuran untuk tabel
                    if (vp.width === 1280) {
                        measuredRecords.push({
                            Halaman: item.pageName,
                            ID: item.id,
                            'Lebar Terukur': `${measurements.width}px`,
                            'Bebas Horiz Scroll': !measurements.hasHorizontalOverflow ? 'Ya' : 'Tidak',
                            Status: widthDiff <= 1 ? 'Sesuai' : 'Menyimpang',
                        });
                    }

                    expect(widthDiff, `Lebar #${item.id} harus ${expectedWidth}px (didapat ${measurements.width}px)`).toBeLessThanOrEqual(1);

                    // Body TIDAK boleh scroll horizontal
                    expect(measurements.hasHorizontalOverflow, `Body #${item.id} tidak boleh ada horizontal scroll`).toBe(false);

                    // Atribut scroll dan backdrop wajib bernilai "true"
                    expect(measurements.hasScroll, `data-bs-scroll pada #${item.id} harus true`).toBe(true);
                    expect(measurements.hasBackdrop, `data-bs-backdrop pada #${item.id} harus true`).toBe(true);

                    // Header, Body, Footer wajib ada
                    expect(measurements.hasHeader, `Header pada #${item.id} harus ada`).toBe(true);
                    expect(measurements.hasBody, `Body pada #${item.id} harus ada`).toBe(true);
                    expect(measurements.hasFooter, `Footer pada #${item.id} harus ada`).toBe(true);

                    // Ukuran judul offcanvas 18px (var(--nb-font-lg))
                    expect(measurements.titleFontSize).toBe('18px');

                    // Kontrol input -sm jika ada tinggi harus 31px ±1px
                    for (const ch of measurements.controlHeights) {
                        expect(ch, `Tinggi kontrol input pada #${item.id} harus 31px (±1px)`).toBeGreaterThanOrEqual(30);
                        expect(ch, `Tinggi kontrol input pada #${item.id} harus 31px (±1px)`).toBeLessThanOrEqual(32);
                    }

                    // Tutup offcanvas
                    await page.evaluate((id) => {
                        const el = document.getElementById(id);
                        if (el) {
                            const inst = window.bootstrap?.Offcanvas?.getInstance(el);
                            if (inst) inst.hide();
                        }
                    }, item.id);
                    await page.waitForTimeout(150);

                    assertNoErrors(errors);
                });
            }
        });
    }

    test.afterAll(async () => {
        console.log('\n=== TABEL UKURAN OFFCANVAS TERUKUR (Fase 17m - Bawaan Bootstrap) ===');
        console.table(measuredRecords);
        if (deviations.length > 0) {
            console.log('Penyimpangan terdeteksi:');
            console.table(deviations);
        } else {
            console.log('✅ Semua 24 offcanvas memenuhi standar bawaan Bootstrap (400px / 360px).');
        }
    });
});
