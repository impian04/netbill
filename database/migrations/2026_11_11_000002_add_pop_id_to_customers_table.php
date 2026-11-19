<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasCustomers = DB::table('customers')->exists();
        $defaultPopId = null;

        if ($hasCustomers) {
            $defaultPopId = DB::table('pops')->insertGetId([
                'code'                 => 'KTU',
                'name'                 => 'Kota Tua',
                'va_code'              => '01',
                'is_active'            => true,
                'last_customer_number' => DB::table('customers')->count(),
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        Schema::table('customers', function (Blueprint $table) use ($defaultPopId) {
            if ($defaultPopId !== null) {
                $table->foreignId('pop_id')->after('id')->default($defaultPopId)->constrained('pops')->cascadeOnUpdate()->restrictOnDelete();
            } else {
                $table->foreignId('pop_id')->after('id')->constrained('pops')->cascadeOnUpdate()->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['pop_id']);
            $table->dropColumn('pop_id');
        });
    }
};

