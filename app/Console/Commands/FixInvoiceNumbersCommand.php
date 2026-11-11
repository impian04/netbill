<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class FixInvoiceNumbersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netbill:fix-invoice-numbers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perbaiki semua nomor tagihan berawalan DRAFT- menjadi format resmi INV-YYYYMM-XXXXXX';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memeriksa nomor tagihan berawalan DRAFT-...');

        $fixed = Invoice::fixDraftNumbers();

        if ($fixed === 0) {
            $this->info('Tidak ada nomor tagihan DRAFT- yang perlu diperbaiki.');
        } else {
            $this->info("Berhasil memperbaiki {$fixed} nomor tagihan.");
        }

        return self::SUCCESS;
    }
}
