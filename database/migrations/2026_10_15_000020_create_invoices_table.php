<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('period'); // normalized to 1st of month
            $table->decimal('deferred_amount', 12, 0);
            $table->enum('status', ['PAID', 'UNPAID'])->default('UNPAID');
            $table->timestamps();

            $table->unique(['customer_id', 'period']);
            $table->index(['period', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
