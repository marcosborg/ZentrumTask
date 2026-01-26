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
        if (! Schema::hasTable('driver_settlements')) {
            Schema::create('driver_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('net_total', 10, 2);
                $table->decimal('tips_total', 10, 2);
                $table->decimal('company_share', 10, 2);
                $table->decimal('driver_share', 10, 2);
                $table->decimal('amount_payable', 10, 2);
                $table->json('rules_snapshot');
                $table->timestamps();

                $table->unique(['driver_id', 'period_start', 'period_end'], 'driver_period_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_settlements');
    }
};
