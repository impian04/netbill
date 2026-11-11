<?php

namespace Tests\Feature;

use App\Jobs\ProcessImport;
use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_admin_form_creates_invoice_with_official_format_matching_regex(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->admin)->postJson('/invoices', [
            'customer_id'     => $customer->id,
            'period'          => '2026-11',
            'deferred_amount' => 250000,
            'status'          => 'UNPAID',
        ]);

        $response->assertStatus(201);
        $numberInResponse = $response->json('data.invoice_number');
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', $numberInResponse);
        $this->assertStringStartsWith('INV-202611-', $numberInResponse);

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', $invoice->invoice_number);
        $this->assertStringStartsWith('INV-202611-', $invoice->invoice_number);
        $this->assertFalse(str_starts_with($invoice->invoice_number, 'DRAFT-'));
    }

    public function test_admin_customer_scoped_route_creates_invoice_with_official_format(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->admin)->postJson("/customers/{$customer->id}/invoices", [
            'period'          => '2026-12',
            'deferred_amount' => 300000,
            'status'          => 'PAID',
        ]);

        $response->assertStatus(201);
        $numberInResponse = $response->json('data.invoice_number');
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', $numberInResponse);
        $this->assertStringStartsWith('INV-202612-', $numberInResponse);
    }

    public function test_import_csv_creates_invoices_with_official_format_and_no_draft_remains(): void
    {
        Storage::fake('local');

        $csvContent = implode("\n", [
            'ID Pelanggan,Nama,Nomor Telepon,Alamat,Tanggal Mulai Berlangganan,Jenis Tagihan,Paket,Status Layanan,Periode,Nominal Ditangguhkan,Status',
            'SLX-GS-00101,Budi Import,081234567890,Jl. Melati No. 1,2026-01-01,Pascabayar,Paket 20 Mbps,ACTIVE,2026-07,150000,UNPAID',
            'SLX-GS-00102,Siti Import,081234567891,Jl. Mawar No. 2,2026-01-01,Pascabayar,Paket 50 Mbps,ACTIVE,2026-07,350000,PAID',
        ]);

        $path = 'imports/test_sample.csv';
        Storage::put($path, $csvContent);

        $batch = ImportBatch::create([
            'original_name'    => 'test_sample.csv',
            'file_path'        => $path,
            'total_rows'       => 2,
            'duplicate_policy' => 'update',
            'status'           => 'queued',
        ]);

        (new ProcessImport($batch))->handle();

        $batch->refresh();
        $this->assertEquals('done', $batch->status);
        $this->assertEquals(2, $batch->created_count);

        $invoices = Invoice::all();
        $this->assertCount(2, $invoices);

        foreach ($invoices as $inv) {
            $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', $inv->invoice_number);
            $this->assertStringStartsWith('INV-202607-', $inv->invoice_number);
            $this->assertFalse(str_starts_with($inv->invoice_number, 'DRAFT-'));
        }

        $this->assertEquals(0, Invoice::where('invoice_number', 'like', 'DRAFT-%')->count());
    }

    public function test_fix_invoice_numbers_command_fixes_draft_numbers_and_leaves_correct_numbers_untouched(): void
    {
        $customer = Customer::factory()->create();

        // 1. Tagihan dengan nomor yang sudah benar
        $correctInvoice = Invoice::factory()->create([
            'customer_id'    => $customer->id,
            'period'         => '2026-03-01',
        ]);
        $correctInvoice->forceFill(['invoice_number' => 'INV-202603-000999'])->saveQuietly();

        // 2. Tagihan buatan dengan nomor DRAFT-
        $draft1 = Invoice::factory()->create([
            'customer_id'    => $customer->id,
            'period'         => '2026-04-01',
        ]);
        $draft1->forceFill(['invoice_number' => 'DRAFT-manual-001'])->saveQuietly();

        $draft2 = Invoice::factory()->create([
            'customer_id'    => $customer->id,
            'period'         => '2026-05-01',
        ]);
        $draft2->forceFill(['invoice_number' => 'DRAFT-manual-002'])->saveQuietly();

        $this->assertEquals(2, Invoice::where('invoice_number', 'like', 'DRAFT-%')->count());

        // Jalankan artisan command
        $this->artisan('netbill:fix-invoice-numbers')
            ->expectsOutputToContain('Berhasil memperbaiki 2 nomor tagihan.')
            ->assertExitCode(0);

        // Pastikan tidak ada DRAFT- tersisa
        $this->assertEquals(0, Invoice::where('invoice_number', 'like', 'DRAFT-%')->count());

        // Nomor yang sudah benar TIDAK berubah
        $correctInvoice->refresh();
        $this->assertEquals('INV-202603-000999', $correctInvoice->invoice_number);

        // Nomor draft diperbaiki ke format resmi
        $draft1->refresh();
        $draft2->refresh();
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', $draft1->invoice_number);
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', $draft2->invoice_number);
        $this->assertStringStartsWith('INV-202604-', $draft1->invoice_number);
        $this->assertStringStartsWith('INV-202605-', $draft2->invoice_number);

        // Uji idempoten: jalankan lagi, laporkan 0 diperbaiki
        $this->artisan('netbill:fix-invoice-numbers')
            ->expectsOutputToContain('Tidak ada nomor tagihan DRAFT- yang perlu diperbaiki.')
            ->assertExitCode(0);
    }

    public function test_invoice_factory_creates_official_format(): void
    {
        $invoice = Invoice::factory()->create([
            'period' => '2026-08-01',
        ]);

        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', $invoice->invoice_number);
        $this->assertStringStartsWith('INV-202608-', $invoice->invoice_number);
        $this->assertFalse(str_starts_with($invoice->invoice_number, 'DRAFT-'));
    }
}
