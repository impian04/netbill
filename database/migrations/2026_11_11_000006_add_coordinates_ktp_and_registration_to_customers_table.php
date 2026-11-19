<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('ktp_path')->nullable()->after('longitude');
            $table->string('registered_via')->default('admin')->after('service_status');
            $table->foreignId('referred_by_user_id')->nullable()->after('registered_via')->constrained('users')->nullOnDelete();
        });

        // Set data lama ke 'import'
        DB::table('customers')->update(['registered_via' => 'import']);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropColumn([
                'latitude',
                'longitude',
                'ktp_path',
                'registered_via',
                'referred_by_user_id',
            ]);
        });
    }
};

