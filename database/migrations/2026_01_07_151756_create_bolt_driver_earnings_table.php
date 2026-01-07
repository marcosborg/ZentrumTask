<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bolt_driver_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bolt_sync_run_id')->constrained('bolt_sync_runs')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bolt_driver_identifier')->nullable()->index();
            $table->string('bolt_driver_name')->nullable()->index();
            $table->string('bolt_driver_email')->nullable()->index();
            $table->date('week_start')->index();
            $table->date('week_end')->index();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('EUR');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['bolt_driver_identifier', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bolt_driver_earnings');
    }
};
