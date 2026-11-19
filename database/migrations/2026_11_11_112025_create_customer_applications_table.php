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
        Schema::create('customer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('marketing_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('package');
            $table->string('billing_type')->default('Bulanan');
            $table->text('notes')->nullable();
            $table->string('ktp_path')->nullable();
            $table->string('status', 30)->default('SUBMITTED'); // SUBMITTED, NEEDS_REVISION, APPROVED, REJECTED
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamps();

            $table->index(['pop_id', 'status']);
            $table->index(['marketing_user_id', 'status']);
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_applications');
    }
};
