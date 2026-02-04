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
        Schema::create('via_verde_transactions', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->string('vehicle_plate')->nullable();
            $table->string('location')->nullable();
            $table->string('type')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('external_ref')->index();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assignment_status')->default('unassigned_driver');
            $table->json('raw_row')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('source_file')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_id', 'external_ref'], 'via_verde_vehicle_external_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('via_verde_transactions');
    }
};
