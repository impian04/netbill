<?php

namespace Tests\Feature;

use App\Jobs\ProcessImport;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Pop::factory()->create(['code' => 'KTU']);
        $this->admin = User::factory()->create();
    }

    private function createValidCsvFile(): UploadedFile
    {
        $headers = [
            'ID Pelanggan', 'Nama', 'Nomor Telepon', 'Alamat',
            'Tanggal Mulai Berlangganan', 'Jenis Tagihan', 'Paket', 'Status Layanan',
            'Periode', 'Nominal Ditangguhkan', 'Status', 'Jatuh Tempo',
        ];
        $row1 = [
            'JCN-KTU-0201', 'Ahmad Test', '081234567890', 'Jl. Kenanga No. 1',
            '2026-01-01', 'Pascabayar', 'Paket 20 Mbps', 'ACTIVE',
            '2026-10', '150000', 'UNPAID', '2026-10-25',
        ];

        $content = implode(',', $headers) . "\n" . implode(',', $row1) . "\n";

        return UploadedFile::fake()->createWithContent('import_test.csv', $content);
    }

    public function test_upload_valid_csv_redirects_to_preview_and_preview_can_be_refreshed(): void
    {
        Storage::fake('local');

        $file = $this->createValidCsvFile();

        $response = $this->actingAs($this->admin)->post(route('imports.upload'), [
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);

        // Check redirected to imports.preview with token
        $targetUrl = $response->headers->get('Location');
        $this->assertStringContainsString('/imports/preview/', $targetUrl);

        // Follow redirect to preview (status 200)
        $previewResponse = $this->actingAs($this->admin)->get($targetUrl);
        $previewResponse->assertStatus(200);
        $previewResponse->assertSee('Ahmad Test');
        $previewResponse->assertSee('JCN-KTU-0201');

        // Refresh preview page (PRG check)
        $refreshResponse = $this->actingAs($this->admin)->get($targetUrl);
        $refreshResponse->assertStatus(200);
        $refreshResponse->assertSee('Ahmad Test');
    }

    public function test_confirm_with_valid_preview_creates_batch_and_redirects_to_show(): void
    {
        Storage::fake('local');
        Queue::fake();

        $file = $this->createValidCsvFile();

        $uploadResponse = $this->actingAs($this->admin)->post(route('imports.upload'), [
            'file' => $file,
        ]);

        $targetUrl = $uploadResponse->headers->get('Location');
        preg_match('#/imports/preview/([a-zA-Z0-9]+)#', $targetUrl, $matches);
        $token = $matches[1] ?? '';
        $this->assertNotEmpty($token);

        $confirmResponse = $this->actingAs($this->admin)->post(route('imports.confirm'), [
            'token'            => $token,
            'duplicate_policy' => 'skip',
        ]);

        $batch = ImportBatch::first();
        $this->assertNotNull($batch);
        $this->assertEquals('queued', $batch->status);
        $this->assertEquals('skip', $batch->duplicate_policy);

        $confirmResponse->assertRedirect(route('imports.show', $batch));
        $confirmResponse->assertSessionHas('success');

        Queue::assertPushed(ProcessImport::class, function ($job) use ($batch) {
            return $job->batch->id === $batch->id;
        });

        // Ensure session was cleaned up
        $this->assertNull(session()->get("import_preview_{$token}"));
    }

    public function test_confirm_without_preview_or_expired_redirects_to_index_with_error(): void
    {
        $response = $this->actingAs($this->admin)->post(route('imports.confirm'), [
            'token'            => 'non-existent-token',
            'duplicate_policy' => 'skip',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('imports.index'));
        $response->assertSessionHas('error');
    }

    public function test_get_upload_and_get_confirm_redirect_to_index(): void
    {
        $getUpload = $this->actingAs($this->admin)->get('/imports/upload');
        $getUpload->assertStatus(302);
        $getUpload->assertRedirect(route('imports.index'));

        $getConfirm = $this->actingAs($this->admin)->get('/imports/confirm');
        $getConfirm->assertStatus(302);
        $getConfirm->assertRedirect(route('imports.index'));
    }

    public function test_non_numeric_import_batch_segment_does_not_match_show_route(): void
    {
        $response = $this->actingAs($this->admin)->get('/imports/random-text-segment');
        $response->assertStatus(404);
    }

    public function test_other_user_cannot_view_preview_of_another_admin(): void
    {
        Storage::fake('local');

        $file = $this->createValidCsvFile();

        $uploadResponse = $this->actingAs($this->admin)->post(route('imports.upload'), [
            'file' => $file,
        ]);

        $targetUrl = $uploadResponse->headers->get('Location');

        $otherAdmin = User::factory()->create();
        $response = $this->actingAs($otherAdmin)->get($targetUrl);

        $response->assertStatus(302);
        $response->assertRedirect(route('imports.index'));
        $response->assertSessionHas('error');
    }

    private function createTestXlsxFile(): UploadedFile
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        $headers = [
            'ID Pelanggan', 'Nama', 'Nomor Telepon', 'Alamat',
            'Tanggal Mulai Berlangganan', 'Jenis Tagihan', 'Paket', 'Status Layanan',
            'Periode', 'Nominal Ditangguhkan', 'Status', 'Jatuh Tempo',
        ];
        $sheet->fromArray($headers, null, 'A1');

        // Row 2: phone text '081234567890', date text '2023-01-15'
        $sheet->setCellValueExplicit('A2', 'JCN-KTU-0001', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('B2', 'Pelanggan Teks');
        $sheet->setCellValueExplicit('C2', '081234567890', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('D2', 'Jl. Mawar No. 1');
        $sheet->setCellValueExplicit('E2', '2023-01-15', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('F2', 'Bulanan');
        $sheet->setCellValue('G2', '10 Mbps');
        $sheet->setCellValue('H2', 'ACTIVE');
        $sheet->setCellValueExplicit('I2', '2026-10', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('J2', 150000);
        $sheet->setCellValue('K2', 'UNPAID');
        $sheet->setCellValueExplicit('L2', '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        // Row 3: phone numeric 13 digits 6281234567891, date Excel serial 44941 (2023-01-15)
        $sheet->setCellValueExplicit('A3', 'JCN-KTU-0002', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('B3', 'Pelanggan Angka 13 Digit');
        $sheet->setCellValue('C3', 6281234567891);
        $sheet->setCellValue('D3', 'Jl. Melati No. 2');
        $sheet->setCellValue('E3', 44941);
        $sheet->getStyle('E3')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->setCellValue('F3', 'Bulanan');
        $sheet->setCellValue('G3', '20 Mbps');
        $sheet->setCellValue('H3', 'ACTIVE');
        $sheet->setCellValueExplicit('I3', '2026-10', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('J3', 250000);
        $sheet->setCellValue('K3', 'PAID');
        $sheet->setCellValueExplicit('L3', '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        // Row 4: phone without 0 (81234567892), date text DD/MM/YYYY '15/01/2023'
        $sheet->setCellValueExplicit('A4', 'JCN-KTU-0003', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('B4', 'Pelanggan Tanpa Nol');
        $sheet->setCellValue('C4', 81234567892);
        $sheet->setCellValue('D4', 'Jl. Anggrek No. 3');
        $sheet->setCellValueExplicit('E4', '15/01/2023', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('F4', 'Bulanan');
        $sheet->setCellValue('G4', '50 Mbps');
        $sheet->setCellValue('H4', 'ACTIVE');
        $sheet->setCellValueExplicit('I4', '2026-10', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('J4', 350000);
        $sheet->setCellValue('K4', 'UNPAID');
        $sheet->setCellValueExplicit('L4', '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_test_') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tmpFile);

        return new UploadedFile($tmpFile, 'test_sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_xlsx_with_various_phone_and_date_types(): void
    {
        $file = $this->createTestXlsxFile();

        $uploadResponse = $this->actingAs($this->admin)->post(route('imports.upload'), [
            'file' => $file,
        ]);

        $uploadResponse->assertStatus(302);
        $targetUrl = $uploadResponse->headers->get('Location');
        preg_match('#/imports/preview/([a-zA-Z0-9]+)#', $targetUrl, $matches);
        $token = $matches[1] ?? '';
        $this->assertNotEmpty($token);

        // Confirm import
        $confirmResponse = $this->actingAs($this->admin)->post(route('imports.confirm'), [
            'token'            => $token,
            'duplicate_policy' => 'skip',
        ]);

        $batch = ImportBatch::latest()->first();
        $this->assertNotNull($batch);

        $batch->refresh();
        $this->assertEquals('done', $batch->status);
        $this->assertEquals(3, $batch->created_count);
        $this->assertEquals(0, $batch->error_count);

        // Verify Customer 1 (phone text 081234567890 -> 6281234567890, date 2023-01-15)
        $c1 = Customer::where('customer_code', 'JCN-KTU-0001')->first();
        $this->assertNotNull($c1);
        $this->assertEquals('6281234567890', $c1->phone);
        $this->assertEquals('2023-01-15', $c1->subscribed_at->format('Y-m-d'));

        // Verify Customer 2 (phone numeric 13 digits 6281234567891, Excel date serial -> 2023-01-15)
        $c2 = Customer::where('customer_code', 'JCN-KTU-0002')->first();
        $this->assertNotNull($c2);
        $this->assertEquals('6281234567891', $c2->phone);
        $this->assertEquals('2023-01-15', $c2->subscribed_at->format('Y-m-d'));

        // Verify Customer 3 (phone 81234567892 without 0 -> 6281234567892, DD/MM/YYYY -> 2023-01-15)
        $c3 = Customer::where('customer_code', 'JCN-KTU-0003')->first();
        $this->assertNotNull($c3);
        $this->assertEquals('6281234567892', $c3->phone);
        $this->assertEquals('2023-01-15', $c3->subscribed_at->format('Y-m-d'));
    }

    public function test_csv_with_scientific_notation_produces_specific_error_message(): void
    {
        $headers = [
            'ID Pelanggan', 'Nama', 'Nomor Telepon', 'Alamat',
            'Tanggal Mulai Berlangganan', 'Jenis Tagihan', 'Paket', 'Status Layanan',
            'Periode', 'Nominal Ditangguhkan', 'Status', 'Jatuh Tempo',
        ];
        $row = [
            'JCN-KTU-9999', 'Budi Rusak Excel', '6.28128E+12', 'Jl. Rusak No. 1',
            '2026-01-01', 'Bulanan', '10 Mbps', 'ACTIVE',
            '2026-10', '150000', 'UNPAID', '',
        ];

        $content = implode(',', $headers) . "\n" . implode(',', $row) . "\n";
        $file = UploadedFile::fake()->createWithContent('import_sci.csv', $content);

        $uploadResponse = $this->actingAs($this->admin)->post(route('imports.upload'), [
            'file' => $file,
        ]);
        $targetUrl = $uploadResponse->headers->get('Location');
        preg_match('#/imports/preview/([a-zA-Z0-9]+)#', $targetUrl, $matches);
        $token = $matches[1] ?? '';

        $this->actingAs($this->admin)->post(route('imports.confirm'), [
            'token'            => $token,
            'duplicate_policy' => 'skip',
        ]);

        $batch = ImportBatch::latest()->first();
        $batch->refresh();
        $this->assertEquals(1, $batch->error_count);
        $this->assertNotEmpty($batch->errors);

        $errorMessages = collect($batch->errors)->pluck('messages')->flatten()->all();
        $this->assertContains(
            'Nomor telepon rusak oleh Excel. Gunakan template .xlsx atau format kolom sebagai Teks.',
            $errorMessages
        );
    }

    public function test_template_xlsx_download_and_column_format_is_text(): void
    {
        $response = $this->actingAs($this->admin)->get(route('imports.template.xlsx'));
        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));

        // Save downloaded content to temp file to verify with PhpSpreadsheet
        $tmpFile = tempnam(sys_get_temp_dir(), 'tpl_dl_') . '.xlsx';
        file_put_contents($tmpFile, $response->streamedContent());

        $loaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpFile);
        $dataSheet = $loaded->getSheetByName('Data');
        $this->assertNotNull($dataSheet);

        $petunjukSheet = $loaded->getSheetByName('Petunjuk');
        $this->assertNotNull($petunjukSheet);

        // Header bold and frozen
        $this->assertTrue($dataSheet->getStyle('A1')->getFont()->getBold());
        $this->assertEquals('A2', $dataSheet->getFreezePane());

        // Column A (customer_code) & Column C (phone) format is Text (@)
        $this->assertEquals('@', $dataSheet->getStyle('A1')->getNumberFormat()->getFormatCode());
        $this->assertEquals('@', $dataSheet->getStyle('C1')->getNumberFormat()->getFormatCode());

        @unlink($tmpFile);
    }
}
