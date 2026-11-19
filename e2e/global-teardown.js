/**
 * global-teardown.js — Fase 17g
 * Matikan php artisan serve yang berjalan di background.
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root      = path.resolve(__dirname, '..');
const pidFile   = path.join(root, 'e2e/.server.pid');

export default async function globalTeardown() {
    if (fs.existsSync(pidFile)) {
        const pid = parseInt(fs.readFileSync(pidFile, 'utf8').trim(), 10);
        if (!isNaN(pid)) {
            try {
                process.kill(pid, 'SIGTERM');
                console.log(`[E2E] Server (PID ${pid}) dihentikan.`);
            } catch (e) {
                console.warn('[E2E] Gagal menghentikan server:', e.message);
            }
        }
        fs.unlinkSync(pidFile);
    }
}

