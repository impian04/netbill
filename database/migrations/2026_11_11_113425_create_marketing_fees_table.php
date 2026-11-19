<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('marketing_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('pop_id')->constrained('pops')->restrictOnDelete();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('status', 30)->default('PENDING'); // PENDING, PAID
            $table->timestamp('eligible_at');
            $table->timestamp('paid_at')->nullable();
            $table->text('paid_note')->nullable();
            $table->timestamps();

            $table->index(['pop_id', 'status']);
            $table->index(['marketing_user_id', 'status']);
            $table->index('eligible_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_fees');
    }
};
