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
        Schema::create('tesla_vehicle_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tesla_vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('source')->index();
            $table->string('code')->nullable()->index();
            $table->text('message')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->json('raw_payload');
            $table->timestamps();

            $table->index(['tesla_vehicle_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tesla_vehicle_errors');
    }
};
