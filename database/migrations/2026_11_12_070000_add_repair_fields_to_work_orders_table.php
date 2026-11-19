<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('type', 20)->default('INSTALLATION')->after('id');
            $table->unsignedBigInteger('installation_key')->nullable()->unique()->after('customer_id');
            $table->foreignId('ticket_id')->nullable()->after('survey_id')->constrained('tickets')->nullOnDelete();

            // Ubah survey_id dan technician_user_id menjadi nullable
            $table->foreignId('survey_id')->nullable()->change();
            $table->foreignId('technician_user_id')->nullable()->change();

            // Tambahkan index biasa pada customer_id sebelum melepas unique index
            $table->index('customer_id');
            $table->dropUnique(['customer_id']);
            $table->index(['type', 'status']);
        });

        // Set data lama sebagai INSTALLATION dengan installation_key = customer_id
        DB::table('work_orders')->update([
            'type'             => 'INSTALLATION',
            'installation_key' => DB::raw('customer_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->dropColumn(['ticket_id', 'type', 'installation_key']);
            $table->unique('customer_id');
            $table->foreignId('survey_id')->nullable(false)->change();
            $table->foreignId('technician_user_id')->nullable(false)->change();
        });
    }
};

