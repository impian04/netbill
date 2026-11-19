<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // -------------------------------------------------------------------------
    // LAPIS 1 — Pemeriksaan statis sebelum framework Laravel diinisialisasi
    // -------------------------------------------------------------------------
    // PHPUnit memanggil setUpBeforeClass() SEBELUM setUp() manapun dan
    // SEBELUM framework diinisialisasi, sehingga ini adalah pagar paling awal
    // yang masih terjangkau dari kelas test biasa (non-static beforeClass).
    // -------------------------------------------------------------------------

    /**
     * Lapis 1 (paling awal): periksa konfigurasi DB dari env/phpunit
     * SEBELUM framework apa pun diinisialisasi.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::assertSafeTestDatabase();
    }

    /**
     * Periksa nama database dari env — jika tidak berakhiran '_test', hentikan
     * dengan RuntimeException sehingga PHP membuang proses sebelum koneksi DB
     * dibuka. Metode ini dipanggil sedini mungkin (setUpBeforeClass, bukan setUp).
     */
    private static function assertSafeTestDatabase(): void
    {
        // Cek dari variabel lingkungan yang di-set phpunit.xml (force="true")
        $dbName = (string) (
            getenv('DB_DATABASE') ?:
            (isset($_ENV['DB_DATABASE']) ? $_ENV['DB_DATABASE'] : '') ?:
            (isset($_SERVER['DB_DATABASE']) ? $_SERVER['DB_DATABASE'] : '')
        );

        if ($dbName !== '' && ! str_ends_with($dbName, '_test')) {
            throw new RuntimeException(
                "\n\n" .
                "╔══════════════════════════════════════════════════════════════╗\n" .
                "║  PENGAMAN DATABASE AKTIF — TEST DIBATALKAN                  ║\n" .
                "║  Database yang dikonfigurasi TIDAK berakhiran '_test'.       ║\n" .
                "║  Database terdeteksi : '{$dbName}'                          \n" .
                "║  Pastikan phpunit.xml menyetel DB_DATABASE=db_netbill_test  ║\n" .
                "║  dengan force=\"true\" dan tidak ada env luar yang menimpa.   ║\n" .
                "╚══════════════════════════════════════════════════════════════╝\n"
            );
        }
    }

    // -------------------------------------------------------------------------
    // LAPIS 2 — beforeRefreshingDatabase() dari trait RefreshDatabase
    // -------------------------------------------------------------------------
    // RefreshDatabase memanggil $this->beforeRefreshingDatabase() sebagai hook
    // pertama di refreshDatabase(), SEBELUM migrate:fresh dijalankan.
    // -------------------------------------------------------------------------

    /**
     * Lapis 2: dipanggil oleh trait RefreshDatabase SEBELUM migrate:fresh.
     * Verifikasi koneksi DB aktif sesaat sebelum database disentuh.
     * Signature tanpa `: void` agar kompatibel dengan deklarasi trait Laravel 13.
     */
    protected function beforeRefreshingDatabase()
    {
        $this->guardAgainstNonTestDatabase('beforeRefreshingDatabase');
    }

    // -------------------------------------------------------------------------
    // LAPIS 3 — setUp() setelah parent (kompatibilitas mundur & fallback)
    // -------------------------------------------------------------------------

    /**
     * Lapis 3 (fallback): periksa setelah framework siap — berlaku untuk test
     * yang tidak memakai RefreshDatabase atau path eksekusi alternatif.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->guardAgainstNonTestDatabase('setUp');
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Gagalkan test jika koneksi DB yang aktif bukan database *_test.
     * Lapis ini memakai DB facade (tersedia setelah parent::setUp()).
     */
    private function guardAgainstNonTestDatabase(string $calledFrom): void
    {
        try {
            $dbName = (string) DB::connection()->getDatabaseName();
        } catch (\Throwable) {
            // Jika koneksi belum siap, abaikan (lapis 1 sudah menjaga)
            return;
        }

        if (! str_ends_with($dbName, '_test')) {
            $this->fail(
                "PENGAMAN DATABASE AKTIF [{$calledFrom}]: " .
                "Test hanya boleh dijalankan pada database dengan nama berakhiran '_test'. " .
                "Database saat ini: '{$dbName}'. " .
                "Pastikan phpunit.xml menyetel DB_DATABASE=db_netbill_test dengan force=\"true\"."
            );
        }
    }
}
