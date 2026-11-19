<?php

namespace Tests\Feature;

use App\Models\Pop;
use App\Models\User;
use App\Models\Customer;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Survey;
use App\Models\WorkOrder;
use App\Models\Ticket;
use App\Models\CustomerApplication;
use App\Models\MarketingProfile;
use App\Models\MarketingFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UiStructureTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;
    private User $superAdmin;
    private User $admin;
    private User $surveyor;
    private User $technician;
    private User $noc;
    private User $marketing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pop = Pop::factory()->create(['code' => 'KTU', 'name' => 'POP Koto Tuo']);

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->admin      = User::factory()->admin($this->pop)->create();
        $this->surveyor   = User::factory()->surveyor($this->pop)->create();
        $this->technician = User::factory()->technician($this->pop)->create();
        $this->noc        = User::factory()->noc()->create();
        $this->marketing  = User::factory()->marketing($this->pop)->create();
    }

    /**
     * Data provider untuk 13 halaman daftar.
     */
    public static function listingPagesProvider(): array
    {
        return [
            'Pelanggan (Acuan)' => ['customers.index', 'superAdmin', 'resources/views/customers/index.blade.php'],
            'Survei'            => ['surveys.index', 'admin', 'resources/views/surveys/index.blade.php'],
            'Penugasan'         => ['work-orders.index', 'admin', 'resources/views/work_orders/index.blade.php'],
            'Tiket'             => ['tickets.index', 'noc', 'resources/views/tickets/index.blade.php'],
            'Cari Pelanggan'    => ['noc.customers.index', 'noc', 'resources/views/noc/customers/index.blade.php'],
            'Antrean Survei'    => ['surveyor.queue.index', 'surveyor', 'resources/views/surveyor/queue.blade.php'],
            'Survei Saya'       => ['surveyor.surveys.index', 'surveyor', 'resources/views/surveyor/surveys.blade.php'],
            'Tugas Saya'        => ['technician.tasks.index', 'technician', 'resources/views/technician/tasks.blade.php'],
            'Pendaftaran'       => ['customer-applications.index', 'admin', 'resources/views/customer_applications/index.blade.php'],
            'Marketing'         => ['marketing-profiles.index', 'admin', 'resources/views/marketing_profiles/index.blade.php'],
            'Fee Marketing'     => ['marketing-fees.index', 'admin', 'resources/views/marketing_fees/index.blade.php'],
            'Staf'              => ['staff.index', 'superAdmin', 'resources/views/staff/index.blade.php'],
            'POP'               => ['pops.index', 'superAdmin', 'resources/views/pops/index.blade.php'],
        ];
    }

    #[DataProvider('listingPagesProvider')]
    public function test_listing_pages_conform_to_ui_structure_contract(string $routeName, string $userProp, string $viewPath): void
    {
        $user = $this->{$userProp};

        // 1. Periksa file view sumber tidak boleh memiliki tag <style> atau atribut style inline
        $fullPath = base_path($viewPath);
        $this->assertFileExists($fullPath);
        $rawView = file_get_contents($fullPath);

        $this->assertDoesNotMatchRegularExpression(
            '/<style\b/i',
            $rawView,
            "View [{$viewPath}] tidak boleh memiliki tag <style>."
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\bstyle\s*=/i',
            $rawView,
            "View [{$viewPath}] tidak boleh memiliki atribut style inline."
        );

        // 2. Render halaman via GET request
        $response = $this->actingAs($user)->get(route($routeName));
        $response->assertStatus(200);

        $html = $response->getContent();
        
        // Assert tidak ada tag <style> di dalam body respon
        $this->assertDoesNotMatchRegularExpression(
            '/<style\b[^>]*>(?!.*data-vite-dev-id).*?<\/style>/is',
            $html,
            "Respon HTML [{$routeName}] tidak boleh mengandung tag <style> kustom."
        );

        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        // 3. Memiliki kerangka .page-header dengan .page-title dan .page-subtitle
        $pageHeaders = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " page-header ")]');
        $this->assertGreaterThan(0, $pageHeaders->length, "Halaman [{$routeName}] harus memiliki elemen .page-header.");

        $pageTitles = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " page-title ")]', $pageHeaders->item(0));
        $this->assertGreaterThan(0, $pageTitles->length, "Halaman [{$routeName}] harus memiliki .page-title di dalam .page-header.");

        $pageSubtitles = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " page-subtitle ")]', $pageHeaders->item(0));
        $this->assertGreaterThan(0, $pageSubtitles->length, "Halaman [{$routeName}] harus memiliki .page-subtitle di dalam .page-header.");

        // Tombol aksi di page-header (jika ada) harus memiliki kelas btn-sm
        $headerButtons = $xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " btn ")] | .//button[contains(concat(" ", normalize-space(@class), " "), " btn ")]', $pageHeaders->item(0));
        foreach ($headerButtons as $btn) {
            $class = $btn->getAttribute('class') ?? '';
            $this->assertStringContainsString('btn-sm', $class, "Tombol di .page-header halaman [{$routeName}] harus menggunakan btn-sm: '{$btn->textContent}'.");
        }

        // 4. Memiliki kartu filter atau kartu konten (.card)
        $cards = $xpath->query('//*[@id="main-content"]//*[contains(concat(" ", normalize-space(@class), " "), " card ")]');
        $this->assertGreaterThan(0, $cards->length, "Halaman [{$routeName}] harus memiliki elemen .card.");

        // Input dan select pada form filter harus menggunakan ukuran sm (-sm)
        $filterInputs = $xpath->query('//*[@id="main-content"]//form//input[not(@type="hidden") and not(@type="checkbox") and not(@type="radio") and not(@type="file")]');
        foreach ($filterInputs as $input) {
            $class = $input->getAttribute('class') ?? '';
            if (!str_contains($class, 'form-control')) continue;
            $this->assertStringContainsString(
                'form-control-sm',
                $class,
                "Input pada halaman [{$routeName}] harus menggunakan form-control-sm. Ditemukan: class='{$class}'"
            );
        }

        $filterSelects = $xpath->query('//*[@id="main-content"]//form//select');
        foreach ($filterSelects as $select) {
            $class = $select->getAttribute('class') ?? '';
            $this->assertStringContainsString(
                'form-select-sm',
                $class,
                "Select pada halaman [{$routeName}] harus menggunakan form-select-sm. Ditemukan: class='{$class}'"
            );
        }

        // Tombol aksi umum di filter / card harus btn-sm
        $cardButtons = $xpath->query('//*[@id="main-content"]//*[contains(concat(" ", normalize-space(@class), " "), " card ")]//button[contains(concat(" ", normalize-space(@class), " "), " btn ")] | //*[@id="main-content"]//*[contains(concat(" ", normalize-space(@class), " "), " card ")]//a[contains(concat(" ", normalize-space(@class), " "), " btn ")]');
        foreach ($cardButtons as $btn) {
            $class = $btn->getAttribute('class') ?? '';
            if (str_contains($class, 'btn-close')) continue;
            $this->assertStringContainsString(
                'btn-sm',
                $class,
                "Tombol di card pada halaman [{$routeName}] harus menggunakan btn-sm. Ditemukan: class='{$class}' text='{$btn->textContent}'"
            );
        }
    }

    /**
     * Butir 7a: Tidak ada karakter emoji di seluruh berkas view dan JS (\p{Extended_Pictographic}).
     */
    public function test_no_emoji_in_views_and_js_files(): void
    {
        $directories = [resource_path('views'), resource_path('js')];
        $violations = [];

        foreach ($directories as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/\.(blade\.php|js)$/', $file->getFilename())) {
                    $content = file_get_contents($file->getPathname());
                    if (preg_match('/\p{Extended_Pictographic}/u', $content, $matches)) {
                        $relPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                        $violations[] = "{$relPath} (ditemukan: " . bin2hex($matches[0]) . ")";
                    }
                }
            }
        }

        $this->assertEmpty($violations, "Ditemukan emoji terlarang pada berkas:\n" . implode("\n", $violations));
    }

    /**
     * Butir 7b: Tidak ada tag <i class="bi ..."> di berkas view di luar whitelist.
     */
    public function test_no_decorative_bi_icons_in_views_outside_whitelist(): void
    {
        $whitelist = [
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, 'resources/views/components/btn-icon.blade.php'),
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, 'resources/views/layouts/partials/sidebar.blade.php'),
        ];

        $viewsDir = resource_path('views');
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $relPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                if (in_array($relPath, $whitelist, true)) {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                if (preg_match('/<i\s+class=["\'][^"\']*\bbi\s+bi-[^"\']*["\'][^>]*>\s*<\/i>/i', $content, $matches)) {
                    $violations[] = "{$relPath}: {$matches[0]}";
                }
            }
        }

        $this->assertEmpty($violations, "Ditemukan tag ikon <i class=\"bi ...\"> di luar whitelist pada:\n" . implode("\n", $violations));
    }

    /**
     * Butir 7c: Tidak ada tag <button> atau <a> dengan kelas btn-(primary|success|danger|warning|secondary|info) langsung di view di luar komponen x-btn.
     */
    public function test_no_raw_button_variant_classes_in_views_outside_whitelist(): void
    {
        $whitelist = [
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, 'resources/views/components/btn.blade.php'),
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, 'resources/views/components/btn-icon.blade.php'),
        ];

        $viewsDir = resource_path('views');
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $relPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                if (in_array($relPath, $whitelist, true)) {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                if (preg_match('/<(?:button|a)\b[^>]*\bclass=["\'][^"\']*\bbtn-(?:primary|success|danger|warning|secondary|info)\b/i', $content, $matches)) {
                    $violations[] = "{$relPath}: {$matches[0]}";
                }
            }
        }

        $this->assertEmpty($violations, "Ditemukan raw button class di luar komponen <x-btn> pada:\n" . implode("\n", $violations));
    }

    /**
     * Butir 7d: Tidak ada container-fluid px-* py-* ad hoc di view.
     */
    public function test_no_adhoc_container_fluid_in_views(): void
    {
        $viewsDir = resource_path('views');
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $relPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $content = file_get_contents($file->getPathname());
                if (preg_match('/container-fluid\s+px-/i', $content, $matches)) {
                    $violations[] = "{$relPath}: {$matches[0]}";
                }
            }
        }

        $this->assertEmpty($violations, "Ditemukan pembungkus ad-hoc 'container-fluid px-*' pada:\n" . implode("\n", $violations));
    }

    /**
     * Fase 17j (3a): Tidak ada class="offcanvas" di view di luar berkas komponen x-offcanvas-form.
     */
    public function test_no_raw_offcanvas_class_outside_offcanvas_form_component(): void
    {
        $whitelist = [
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, 'resources/views/components/offcanvas-form.blade.php'),
        ];

        $viewsDir = resource_path('views');
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $relPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                if (in_array($relPath, $whitelist, true)) {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                if (preg_match('/class=["\'][^"\']*\boffcanvas\b(?!-)/i', $content, $matches)) {
                    $violations[] = "{$relPath}: {$matches[0]}";
                }
            }
        }

        $this->assertEmpty($violations, "Ditemukan elemen .offcanvas langsung di luar <x-offcanvas-form> pada:\n" . implode("\n", $violations));
    }

    /**
     * Fase 17m: Offcanvas berukuran seragam 600px (--bs-offcanvas-width: 600px).
     * Dilarang ada aturan lebar kustom di view, dilarang token kustom (--nb-offcanvas-width-*),
     * dan dilarang prop size pada x-offcanvas-form.
     */
    public function test_no_adhoc_offcanvas_width_in_views_and_css(): void
    {
        $scanDirs = [
            resource_path('views'),
            resource_path('css'),
        ];

        $violations = [];

        foreach ($scanDirs as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $content = file_get_contents($file->getPathname());

                    // Dilarang memakai override --bs-offcanvas-width di file view
                    if (str_ends_with($file->getFilename(), '.blade.php') && preg_match('/--bs-offcanvas-width/i', $content, $matches)) {
                        $violations[] = "{$relPath}: menggunakan variable --bs-offcanvas-width di view";
                    }

                    // Dilarang memakai token lebar kustom --nb-offcanvas-width-*
                    if (preg_match('/--nb-offcanvas-width/i', $content, $matches)) {
                        $violations[] = "{$relPath}: menggunakan token kustom --nb-offcanvas-width";
                    }

                    // Dilarang memakai kelas lawas crud-offcanvas-wide atau crud-offcanvas
                    if (preg_match('/crud-offcanvas/i', $content, $matches)) {
                        $violations[] = "{$relPath}: menggunakan kelas kustom crud-offcanvas";
                    }

                    // Dilarang memakai prop size="lg" atau size="md" pada x-offcanvas-form
                    if (preg_match('/<x-offcanvas-form[^>]*\bsize=["\'][^"\']+["\']/i', $content, $matches)) {
                        $violations[] = "{$relPath}: menggunakan prop size pada x-offcanvas-form: {$matches[0]}";
                    }

                    // Pada file view, dilarang style inline lebar pada offcanvas
                    if (str_ends_with($file->getFilename(), '.blade.php')) {
                        if (preg_match('/<[^>]*offcanvas[^>]*style=["\'][^"\']*\b(?:min-|max-)?width\s*:/i', $content, $matches)) {
                            $violations[] = "{$relPath}: inline style lebar pada offcanvas: {$matches[0]}";
                        }
                    }

                    // Pada file CSS, dilarang mengatur lebar properti kustom (width/min-width/max-width) langsung pada selektor offcanvas
                    if (str_ends_with($file->getFilename(), '.css')) {
                        if (preg_match('/\.offcanvas[^{]*\{[^}]*(?<!-)\b(?:min-|max-)?width\s*:\s*[^;]+;/i', $content, $matches)) {
                            $violations[] = "{$relPath}: aturan lebar kustom pada selektor offcanvas: {$matches[0]}";
                        }
                    }
                }
            }
        }

        $this->assertEmpty($violations, "Ditemukan aturan lebar kustom pada offcanvas:\n" . implode("\n", $violations));
    }

    /**
     * Fase 17j (3c): Setiap offcanvas yang dirender memiliki atribut data-bs-scroll="true" dan data-bs-backdrop="true".
     */
    public function test_all_offcanvas_components_and_views_have_scroll_and_backdrop_attributes(): void
    {
        $componentPath = resource_path('views/components/offcanvas-form.blade.php');
        $this->assertFileExists($componentPath);
        $componentContent = file_get_contents($componentPath);

        $this->assertMatchesRegularExpression(
            '/data-bs-scroll=["\']true["\']/',
            $componentContent,
            'Komponen x-offcanvas-form wajib memiliki atribut data-bs-scroll="true"'
        );

        $this->assertMatchesRegularExpression(
            '/data-bs-backdrop=["\']true["\']/',
            $componentContent,
            'Komponen x-offcanvas-form wajib memiliki atribut data-bs-backdrop="true"'
        );
    }
}
