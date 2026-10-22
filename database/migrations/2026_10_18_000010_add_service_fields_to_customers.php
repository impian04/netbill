<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('address');
            $table->string('service_status', 20)->default('ACTIVE')->after('billing_type');
            $table->string('password')->nullable()->after('service_status');
            $table->timestamp('activated_at')->nullable()->after('password');
            $table->string('activation_code_hash')->nullable()->after('activated_at');
            $table->timestamp('activation_expires_at')->nullable()->after('activation_code_hash');
            $table->unsignedTinyInteger('activation_attempts')->default(0)->after('activation_expires_at');
        });

        // Backfill existing rows — all become ACTIVE (already default, just explicit)
        \DB::table('customers')->whereNull('service_status')->update(['service_status' => 'ACTIVE']);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'service_status', 'password', 'activated_at',
                'activation_code_hash', 'activation_expires_at', 'activation_attempts',
            ]);
        });
    }
};
