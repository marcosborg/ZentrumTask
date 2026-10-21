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
        Schema::create('vehicle_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('scheduled_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->unsignedInteger('next_odometer')->nullable();
            $table->unsignedSmallInteger('interval_years')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_revisions');
    }
};
