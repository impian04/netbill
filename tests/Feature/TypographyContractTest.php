<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class TypographyContractTest extends TestCase
{
    /**
     * Kontrak PHP: Dilarang menggunakan font-size inline atau kelas ad-hoc fs-* di seluruh view Blade.
     */
    public function test_views_do_not_contain_inline_font_size_or_ad_hoc_fs_classes(): void
    {
        $viewsPath = resource_path('views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));

        $violations = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $content = file_get_contents($file->getPathname());
                $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());

                // 1. Larang style="...font-size:..."
                if (preg_match('/style\s*=\s*["\'][^"\']*font-size\s*:/i', $content, $matches)) {
                    $violations[] = "[{$relativePath}] Mengandung inline style font-size: '{$matches[0]}'";
                }

                // 2. Larang kelas ukuran font ad-hoc fs-1 s/d fs-6
                if (preg_match_all('/\bfs-[1-6]\b/', $content, $matches)) {
                    $uniqueClasses = array_unique($matches[0]);
                    $violations[] = "[{$relativePath}] Mengandung kelas ukuran font ad-hoc: " . implode(', ', $uniqueClasses);
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Ditemukan pelanggaran tipografi pada file view:\n" . implode("\n", $violations)
        );
    }
}

