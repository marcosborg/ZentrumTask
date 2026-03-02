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
        Schema::create('vehicle_weekly_mileages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->decimal('weekly_km', 10, 2);
            $table->string('assignment_status')->default('unassigned_driver');
            $table->json('raw_row')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('source_file')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_id', 'period_start', 'period_end'], 'vehicle_weekly_mileages_unique_period');
            $table->index(['driver_id', 'period_start', 'period_end'], 'vehicle_weekly_mileages_driver_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_weekly_mileages');
    }
};
