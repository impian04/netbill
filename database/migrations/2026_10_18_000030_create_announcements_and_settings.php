<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'published_at', 'expires_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default NOC contacts
        \DB::table('settings')->insert([
            ['key' => 'noc_connection_wa',   'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'noc_payment_wa',      'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'noc_installation_wa', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('settings');
    }
};
