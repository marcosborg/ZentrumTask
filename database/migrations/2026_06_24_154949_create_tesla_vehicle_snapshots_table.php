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
        Schema::create('tesla_vehicle_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tesla_vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at')->index();
            $table->string('vehicle_state')->nullable()->index();
            $table->string('charging_state')->nullable()->index();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->unsignedTinyInteger('usable_battery_level')->nullable();
            $table->decimal('battery_range', 8, 2)->nullable();
            $table->decimal('est_battery_range', 8, 2)->nullable();
            $table->decimal('rated_battery_range', 8, 2)->nullable();
            $table->decimal('odometer', 12, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('heading')->nullable();
            $table->string('shift_state')->nullable();
            $table->decimal('charge_energy_added', 8, 2)->nullable();
            $table->decimal('charger_power', 8, 2)->nullable();
            $table->unsignedTinyInteger('charge_limit_soc')->nullable();
            $table->decimal('inside_temp', 6, 2)->nullable();
            $table->decimal('outside_temp', 6, 2)->nullable();
            $table->decimal('driver_temp_setting', 6, 2)->nullable();
            $table->decimal('passenger_temp_setting', 6, 2)->nullable();
            $table->decimal('tpms_pressure_fl', 6, 2)->nullable();
            $table->decimal('tpms_pressure_fr', 6, 2)->nullable();
            $table->decimal('tpms_pressure_rl', 6, 2)->nullable();
            $table->decimal('tpms_pressure_rr', 6, 2)->nullable();
            $table->json('raw_payload');
            $table->timestamps();

            $table->index(['tesla_vehicle_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tesla_vehicle_snapshots');
    }
};
