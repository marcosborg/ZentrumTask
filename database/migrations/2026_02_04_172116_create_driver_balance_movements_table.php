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
        Schema::create('driver_balance_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_balance_id')->constrained('driver_balances')->cascadeOnDelete();
            $table->foreignId('driver_settlement_id')->nullable()->constrained('driver_settlements')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('type');
            $table->string('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_balance_movements');
    }
};
