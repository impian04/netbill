<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('super_admin')->after('email');
            $table->foreignId('pop_id')->nullable()->after('role')->constrained('pops')->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('pop_id');
            $table->boolean('is_active')->default(true)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['pop_id']);
            $table->dropColumn(['role', 'pop_id', 'phone', 'is_active']);
        });
    }
};

