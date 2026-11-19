/**
 * global-setup.js — Fase 17g
 * 1. Muat .env.e2e
 * 2. Jalankan e2e:prepare (migrate:fresh --seed) di db_netbill_e2e
 * 3. Nyalakan php artisan serve --port=8001 dengan .env.e2e
 */

import { execSync, spawn } from 'child_process';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root      = path.resolve(__dirname, '..');

/** Baca .env.e2e dan setel ke process.env */
function loadE2eEnv() {
    const envPath = path.join(root, '.env.e2e');
    if (!fs.existsSync(envPath)) {
        throw new Error('.env.e2e tidak ditemukan. Buat file tersebut terlebih dahulu.');
    }
    const lines = fs.readFileSync(envPath, 'utf8').split('\n');
    for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) continue;
        const eq = trimmed.indexOf('=');
        if (eq === -1) continue;
        const key = trimmed.slice(0, eq).trim();
        const val = trimmed.slice(eq + 1).trim().replace(/^["']|["']$/g, '');
        // force="true" gaya: setel ke env proses agar artisan mewarisi
        process.env[key] = val;
    }
    console.log('[E2E] .env.e2e dimuat. DB_DATABASE =', process.env.DB_DATABASE);
}

export default async function globalSetup() {
    loadE2eEnv();

    // Pastikan pengaman: DB_DATABASE harus berakhiran _e2e
    if (!process.env.DB_DATABASE?.endsWith('_e2e')) {
        throw new Error(
            `PENGAMAN E2E: DB_DATABASE='${process.env.DB_DATABASE}' tidak berakhiran '_e2e'. Batalkan.`
        );
    }
    if (process.env.APP_ENV !== 'local') {
        throw new Error(
            `PENGAMAN E2E: APP_ENV='${process.env.APP_ENV}' bukan 'local'. Batalkan.`
        );
    }

    // Pastikan tidak ada stale public/hot agar Laravel memuat asset build produksi
    const hotFile = path.join(root, 'public', 'hot');
    if (fs.existsSync(hotFile)) {
        fs.unlinkSync(hotFile);
    }

    console.log('[E2E] Menyiapkan database:', process.env.DB_DATABASE);

    // Jalankan e2e:prepare (migrate:fresh + seed)
    execSync('php artisan e2e:prepare --force', {
        cwd: root,
        stdio: 'inherit',
        env: { ...process.env },
    });

    // Nyalakan php artisan serve --port=8001
    const server = spawn('php', ['artisan', 'serve', '--port=8001'], {
        cwd: root,
        stdio: 'pipe',
        detached: false,
        env: { ...process.env, PATH: process.env.PATH },
    });

    server.stderr?.on('data', (d) => {
        const msg = d.toString().trim();
        if (msg) console.log('[E2E serve]', msg);
    });

    // Simpan PID agar bisa dibunuh di teardown
    fs.writeFileSync(path.join(root, 'e2e/.server.pid'), String(server.pid));

    // Tunggu server siap (maksimal 10 detik)
    await waitForServer('http://127.0.0.1:8001', 10_000);
    console.log('[E2E] Server siap di http://127.0.0.1:8001');
}

async function waitForServer(url, timeoutMs) {
    const start = Date.now();
    while (Date.now() - start < timeoutMs) {
        try {
            const { default: http } = await import('http');
            await new Promise((resolve, reject) => {
                const req = http.get(url, (res) => {
                    res.destroy();
                    resolve();
                });
                req.on('error', reject);
                req.setTimeout(1000, () => { req.destroy(); reject(new Error('timeout')); });
            });
            return; // Server siap
        } catch {
            await new Promise((r) => setTimeout(r, 500));
        }
    }
    throw new Error(`Server di ${url} tidak merespons setelah ${timeoutMs}ms`);
}

