<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained('surveys')->restrictOnDelete();
            $table->foreignId('pop_id')->constrained('pops')->restrictOnDelete();
            $table->foreignId('technician_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('ASSIGNED');
            $table->date('scheduled_date')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('technician_notes')->nullable();
            $table->date('completed_on')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->date('activated_on')->nullable();
            $table->timestamps();

            $table->index(['pop_id', 'status']);
            $table->index('technician_user_id');
        });

        Schema::create('work_order_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('stage', 20)->default('INSTALASI');
            $table->string('caption', 255)->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_photos');
        Schema::dropIfExists('work_orders');
    }
};

