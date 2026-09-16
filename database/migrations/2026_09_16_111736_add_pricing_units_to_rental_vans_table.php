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
        Schema::table('rental_vans', function (Blueprint $table): void {
            $table->string('self_drive_pricing_unit')->default('hour')->after('self_drive_rate');
            $table->string('with_driver_pricing_unit')->default('hour')->after('with_driver_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rental_vans', function (Blueprint $table): void {
            $table->dropColumn(['self_drive_pricing_unit', 'with_driver_pricing_unit']);
        });
    }
};
