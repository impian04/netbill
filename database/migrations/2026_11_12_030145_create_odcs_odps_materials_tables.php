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
        Schema::create('odcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->constrained('pops')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pop_id', 'name']);
        });

        Schema::create('odps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->constrained('pops')->cascadeOnDelete();
            $table->foreignId('odc_id')->constrained('odcs')->restrictOnDelete();
            $table->string('name', 100);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pop_id', 'name']);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->constrained('pops')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('unit', 20); // meter, pcs, unit, roll, set
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pop_id', 'name']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('odc_id')->nullable()->after('pop_id')->constrained('odcs')->nullOnDelete();
            $table->foreignId('odp_id')->nullable()->after('odc_id')->constrained('odps')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['odc_id']);
            $table->dropForeign(['odp_id']);
            $table->dropColumn(['odc_id', 'odp_id']);
        });

        Schema::dropIfExists('materials');
        Schema::dropIfExists('odps');
        Schema::dropIfExists('odcs');
    }
};
