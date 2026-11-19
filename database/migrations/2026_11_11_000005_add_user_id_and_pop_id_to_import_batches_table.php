<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('duplicate_policy')->constrained('users')->nullOnDelete();
            $table->foreignId('pop_id')->nullable()->after('user_id')->constrained('pops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['pop_id']);
            $table->dropColumn(['user_id', 'pop_id']);
        });
    }
};

