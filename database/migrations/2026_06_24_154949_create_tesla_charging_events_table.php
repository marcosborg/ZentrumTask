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
        Schema::create('tesla_charging_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tesla_vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('source')->index();
            $table->string('external_id')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->decimal('energy_kwh', 10, 3)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('location_name')->nullable();
            $table->string('country')->nullable();
            $table->json('raw_payload');
            $table->timestamps();

            $table->unique(['tesla_vehicle_id', 'source', 'external_id'], 'tesla_charging_event_unique');
            $table->index(['tesla_vehicle_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tesla_charging_events');
    }
};
