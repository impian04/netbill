// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * playwright.config.js — Fase 17g
 * Base URL dan konfigurasi E2E untuk NetBill.
 * Server dijalankan oleh globalSetup sebelum test.
 */
export default defineConfig({
    testDir: './e2e',
    timeout: 30_000,
    retries: 0,
    workers: 1,           // Sequential — satu server PHP, satu DB
    reporter: [
        ['list'],
        ['html', { outputFolder: 'e2e/report', open: 'never' }],
    ],
    use: {
        baseURL: 'http://127.0.0.1:8001',
        // Tangkap semua page error dan console.error
        video: 'off',
        screenshot: 'only-on-failure',
    },
    globalSetup:    './e2e/global-setup.js',
    globalTeardown: './e2e/global-teardown.js',
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], headless: true },
        },
    ],
});

