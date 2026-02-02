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
        Schema::create('prio_transactions', function (Blueprint $table) {
            $table->id();
            $table->dateTime('occurred_at');
            $table->string('card_code');
            $table->string('vehicle_plate')->nullable();
            $table->string('id_usage');
            $table->string('station_id')->nullable();
            $table->decimal('energy_kwh', 10, 3)->nullable();
            $table->decimal('net_amount', 10, 2);
            $table->decimal('gross_amount', 10, 2)->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('assignment_status');
            $table->json('raw_row')->nullable();
            $table->dateTime('imported_at');
            $table->string('source_file')->nullable();
            $table->timestamps();

            $table->index('occurred_at');
            $table->index('card_code');
            $table->index('vehicle_id');
            $table->index('driver_id');
            $table->unique(['card_code', 'id_usage'], 'prio_card_usage_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prio_transactions');
    }
};
