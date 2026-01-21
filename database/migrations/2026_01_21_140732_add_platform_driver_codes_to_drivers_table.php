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
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('bolt_driver_code')->nullable()->unique('drivers_bolt_driver_code_unique');
            $table->string('uber_driver_code')->nullable()->unique('drivers_uber_driver_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique('drivers_bolt_driver_code_unique');
            $table->dropUnique('drivers_uber_driver_code_unique');
            $table->dropColumn(['bolt_driver_code', 'uber_driver_code']);
        });
    }
};
