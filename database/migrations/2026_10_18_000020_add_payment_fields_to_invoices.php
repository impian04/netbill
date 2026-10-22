<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number', 30)->nullable()->unique()->after('id');
            $table->date('due_date')->nullable()->after('period');
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->string('payment_reference', 100)->nullable()->after('paid_at');
        });

        // Backfill invoice_number for existing rows: INV-YYYYMM-{id 6 digits}
        if (\DB::getDriverName() === 'sqlite') {
            \DB::statement("
                UPDATE invoices
                SET invoice_number = 'INV-' || strftime('%Y%m', period) || '-' || substr('000000' || id, -6)
                WHERE invoice_number IS NULL
            ");
        } else {
            \DB::statement("
                UPDATE invoices
                SET invoice_number = CONCAT('INV-', DATE_FORMAT(period, '%Y%m'), '-', LPAD(id, 6, '0'))
                WHERE invoice_number IS NULL
            ");
        }

        // Make NOT NULL after backfill
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number', 30)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['invoice_number', 'due_date', 'paid_at', 'payment_reference']);
        });
    }
};
