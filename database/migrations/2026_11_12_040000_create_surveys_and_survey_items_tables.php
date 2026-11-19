<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('pop_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('surveyor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('odc_id')->nullable()->constrained('odcs')->nullOnDelete();
            $table->foreignId('odp_id')->nullable()->constrained('odps')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('result', 20); // FEASIBLE, NOT_FEASIBLE
            $table->text('result_note')->nullable();
            $table->string('status', 30)->default('SUBMITTED'); // SUBMITTED, RESURVEY_REQUESTED, ASSIGNED, CLOSED
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['pop_id', 'status']);
            $table->index('surveyor_user_id');
        });

        Schema::create('survey_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->timestamps();

            $table->unique(['survey_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_items');
        Schema::dropIfExists('surveys');
    }
};

