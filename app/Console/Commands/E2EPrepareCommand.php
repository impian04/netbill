<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * e2e:prepare — Fase 17g
 *
 * Menyiapkan database E2E (migrate:fresh --seed).
 * WAJIB ditolak kecuali:
 *   1. DB_DATABASE berakhiran "_e2e"
 *   2. APP_ENV = local
 *
 * Pengecekan dilakukan SEBELUM menyentuh database (Lapis Statis dari env, bukan koneksi DB).
 */
class E2EPrepareCommand extends Command
{
    protected $signature   = 'e2e:prepare {--force : Lewati konfirmasi interaktif}';
    protected $description = 'Siapkan database E2E: migrate:fresh --seed (hanya untuk DB *_e2e dan APP_ENV=local)';

    public function handle(): int
    {
        // ── LAPIS 1: Cek statis dari env SEBELUM membuka koneksi DB ───────────
        $this->guardBeforeDatabaseAccess();

        // ── LAPIS 2: Konfirmasi interaktif (kecuali --force atau CI) ──────────
        if (! $this->option('force') && ! app()->runningUnitTests()) {
            $dbName = config('database.connections.mysql.database');
            if (! $this->confirm("Ini akan menghapus SEMUA DATA di [{$dbName}]. Lanjutkan?", false)) {
                $this->warn('Dibatalkan.');
                return self::FAILURE;
            }
        }

        // ── LAPIS 3: Verifikasi koneksi aktual setelah DB::connection() ──────
        try {
            $connectedDb = (string) DB::connection()->getDatabaseName();
        } catch (\Throwable $e) {
            $this->error('Gagal terhubung ke database: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (! str_ends_with($connectedDb, '_e2e')) {
            $this->error(
                "PENGAMAN E2E: Koneksi aktual mengarah ke '{$connectedDb}' (bukan *_e2e). " .
                'Pastikan .env.e2e dimuat dan DB_DATABASE berakhiran _e2e.'
            );
            return self::FAILURE;
        }

        // ── Jalankan migrate:fresh --seed ─────────────────────────────────────
        $this->info("Mempersiapkan database E2E: {$connectedDb}");

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed',       ['--force' => true]);

        $this->info('Database E2E siap.');
        return self::SUCCESS;
    }

    /**
     * Lapis statis: periksa dari getenv() SEBELUM framework membuka koneksi DB.
     * Melempar RuntimeException agar proses berhenti sebelum menyentuh data.
     */
    private function guardBeforeDatabaseAccess(): void
    {
        $dbName  = (string) (getenv('DB_DATABASE')  ?: config('database.connections.mysql.database', ''));
        $appEnv  = (string) (getenv('APP_ENV')       ?: config('app.env', ''));

        if (! str_ends_with($dbName, '_e2e')) {
            throw new RuntimeException(
                "\n\n" .
                "╔══════════════════════════════════════════════════════════════╗\n" .
                "║  PENGAMAN E2E:PREPARE — PERINTAH DIBATALKAN                 ║\n" .
                "║  DB_DATABASE harus berakhiran '_e2e'.                        ║\n" .
                "║  Database terdeteksi : '{$dbName}'                           \n" .
                "║  Muat .env.e2e: php artisan e2e:prepare dengan              ║\n" .
                "║  APP_ENV=local dan DB_DATABASE=db_netbill_e2e               ║\n" .
                "╚══════════════════════════════════════════════════════════════╝\n"
            );
        }

        if ($appEnv !== 'local') {
            throw new RuntimeException(
                "\n\n" .
                "╔══════════════════════════════════════════════════════════════╗\n" .
                "║  PENGAMAN E2E:PREPARE — PERINTAH DIBATALKAN                 ║\n" .
                "║  APP_ENV harus 'local' untuk menjalankan e2e:prepare.        ║\n" .
                "║  APP_ENV terdeteksi : '{$appEnv}'                            \n" .
                "╚══════════════════════════════════════════════════════════════╝\n"
            );
        }
    }
}

