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
        Schema::create('driver_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_balance', 10, 2)->default(0);
            $table->foreignId('last_settlement_id')->nullable()->constrained('driver_settlements')->nullOnDelete();
            $table->boolean('is_settled')->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique('driver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_balances');
    }
};
