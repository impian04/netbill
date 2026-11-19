/**
 * helpers.js — Fase 17g
 * Utilitas bersama untuk seluruh skenario E2E NetBill.
 */

import { expect } from '@playwright/test';

/** Akun demo per role (dari DatabaseSeeder) */
export const ACCOUNTS = {
    super_admin:    { email: 'superadmin@netbill.test',    password: 'password' },
    admin:          { email: 'admin.ktu@netbill.test',     password: 'password' },
    admin_ktu:      { email: 'admin.ktu@netbill.test',     password: 'password' },
    marketing:      { email: 'marketing.ktu@netbill.test', password: 'password' },
    marketing_ktu:  { email: 'marketing.ktu@netbill.test', password: 'password' },
    surveyor:       { email: 'surveyor.ktu@netbill.test',  password: 'password' },
    surveyor_ktu:   { email: 'surveyor.ktu@netbill.test',  password: 'password' },
    technician:     { email: 'technician.ktu@netbill.test', password: 'password' },
    technician_ktu: { email: 'technician.ktu@netbill.test', password: 'password' },
    admin_ep:       { email: 'admin.ep@netbill.test',      password: 'password' },
    marketing_ep:   { email: 'marketing.ep@netbill.test',  password: 'password' },
    marketing2_ep:  { email: 'marketing2.ep@netbill.test', password: 'password' },
    surveyor_ep:    { email: 'surveyor.ep@netbill.test',   password: 'password' },
    technician_ep:  { email: 'technician.ep@netbill.test', password: 'password' },
    noc:            { email: 'noc@netbill.test',           password: 'password' },
};

/**
 * Respons 4xx/5xx yang boleh diabaikan (dicatat tapi tidak fail).
 * Tambahkan URL pattern di sini jika ada endpoint yang memang mengembalikan 4xx.
 */
const IGNORED_RESPONSE_PATTERNS = [
    /\/logout/,
];

/**
 * Login ke aplikasi dan kembalikan page yang sudah terautentikasi.
 * @param {import('@playwright/test').Page} page
 * @param {'super_admin'|'admin'|'marketing'|'surveyor'|'technician'|'noc'} role
 */
export async function loginAs(page, role) {
    const account = ACCOUNTS[role];
    if (!account) throw new Error(`Role tidak dikenal: ${role}`);

    await page.context().clearCookies();
    await page.goto('/login');
    await page.fill('input[name="email"]', account.email);
    await page.fill('input[name="password"]', account.password);
    await page.click('button[type="submit"]');

    // Tunggu redirect selesai (bukan halaman login lagi)
    await page.waitForURL((url) => !url.toString().includes('/login'), { timeout: 10_000 });
}

/**
 * Pasang monitor error pada page dan kembalikan array koleksi error.
 * Panggil sebelum navigasi; periksa setelah aksi selesai.
 *
 * @param {import('@playwright/test').Page} page
 * @returns {{ pageErrors: string[], consoleErrors: string[], networkErrors: string[] }}
 */
export function attachErrorMonitor(page) {
    const pageErrors    = [];
    const consoleErrors = [];
    const networkErrors = [];

    page.on('pageerror', (err) => pageErrors.push(err.message));

    page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    page.on('response', (resp) => {
        const status = resp.status();
        const url    = resp.url();
        if (status >= 400 && !IGNORED_RESPONSE_PATTERNS.some((p) => p.test(url))) {
            networkErrors.push(`${status} ${url}`);
        }
    });

    return { pageErrors, consoleErrors, networkErrors };
}

/**
 * Assert tidak ada error apapun pada page.
 */
export function assertNoErrors({ pageErrors, consoleErrors, networkErrors }) {
    expect(pageErrors,    `Page errors: ${pageErrors.join('\n')}`).toHaveLength(0);
    expect(consoleErrors, `Console errors: ${consoleErrors.join('\n')}`).toHaveLength(0);
    expect(networkErrors, `Network errors (4xx/5xx): ${networkErrors.join('\n')}`).toHaveLength(0);
}

/**
 * Tunggu hingga tidak ada request jaringan aktif (network idle).
 * Berguna setelah trigger filter/pencarian AJAX.
 */
export async function waitForNetworkIdle(page, timeout = 5_000) {
    await page.waitForLoadState('networkidle', { timeout }).catch(() => {
        // Timeout networkidle bukan error fatal — lanjutkan saja
    });
}

