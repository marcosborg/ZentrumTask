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
        Schema::create('platform_driver_balances', function (Blueprint $table) {
            $table->id();
            $table->enum('platform', ['bolt', 'uber']);
            $table->string('driver_code');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('net_amount', 10, 2);
            $table->decimal('tips_amount', 10, 2);
            $table->string('source_file')->nullable();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->unique(['platform', 'driver_code', 'period_start', 'period_end'], 'platform_driver_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_driver_balances');
    }
};
