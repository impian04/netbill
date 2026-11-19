<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class UiConventionsTest extends TestCase
{
    /**
     * Pastikan semua view Blade tidak memanggil pola terlarang secara langsung.
     * Semua view harus menggunakan komponen Blade (x-page-header, x-filter-card, x-table-card, x-offcanvas-form)
     * dan helper JS terpusat (window.initServerTable, window.initCrudOffcanvas, confirmDialog, promptReason, apiRequest).
     */
    public function test_views_do_not_contain_prohibited_patterns(): void
    {
        $viewsPath = resource_path('views');
        $this->assertDirectoryExists($viewsPath);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($viewsPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $prohibitedPatterns = [
            'new DataTable('           => 'Gunakan window.initServerTable() alih-alih new DataTable() langsung di view Blade.',
            '.DataTable('              => 'Gunakan window.initServerTable() alih-alih $(...).DataTable() langsung di view Blade.',
            'Swal.fire('               => 'Gunakan window.confirmDialog() atau window.promptReason() alih-alih Swal.fire() langsung di view Blade.',
            'new bootstrap.'           => 'Gunakan helper window.showModal / window.showOffcanvas atau window.initCrudOffcanvas alih-alih new bootstrap.* langsung di view Blade.',
            'new window.bootstrap.'    => 'Gunakan helper window.showModal / window.showOffcanvas atau window.initCrudOffcanvas alih-alih new window.bootstrap.* langsung di view Blade.',
        ];

        $violations = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $content = file_get_contents($file->getPathname());
                $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());

                foreach ($prohibitedPatterns as $pattern => $message) {
                    if (str_contains($content, $pattern)) {
                        $violations[] = "[{$relativePath}] Mengandung '{$pattern}': {$message}";
                    }
                }

                // Cek form CRUD fetch( langsung di script Blade
                if (preg_match('/(?:form|submitBtn|formData).*?fetch\s*\(/s', $content)) {
                    $violations[] = "[{$relativePath}] Mengandung fetch() langsung untuk form CRUD. Gunakan window.initCrudOffcanvas() atau window.apiRequest().";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Ditemukan pelanggaran konvensi UI pada view Blade:\n" . implode("\n", $violations)
        );
    }
}

