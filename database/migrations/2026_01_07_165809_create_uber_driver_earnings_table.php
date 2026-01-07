<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uber_driver_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uber_sync_run_id')->constrained('uber_sync_runs')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('uber_driver_identifier')->nullable()->index();
            $table->string('uber_driver_uuid')->nullable()->index();
            $table->string('uber_individual_uuid')->nullable()->index();
            $table->string('uber_driver_name')->nullable()->index();
            $table->string('uber_driver_email')->nullable()->index();
            $table->string('driver_name_snapshot')->nullable();
            $table->string('driver_email_snapshot')->nullable();
            $table->boolean('driver_resolved')->default(true)->index();
            $table->date('week_start')->index();
            $table->date('week_end')->index();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('EUR');
            $table->decimal('gross_total', 10, 2)->default(0);
            $table->decimal('gross_app', 10, 2)->default(0);
            $table->decimal('gross_cash', 10, 2)->default(0);
            $table->decimal('net_total', 10, 2)->default(0);
            $table->decimal('expected_payment', 10, 2)->default(0);
            $table->decimal('cash_collected', 10, 2)->default(0);
            $table->decimal('tips', 10, 2)->default(0);
            $table->decimal('commissions', 10, 2)->default(0);
            $table->decimal('total_fees', 10, 2)->default(0);
            $table->decimal('reservation_fees', 10, 2)->default(0);
            $table->decimal('other_fees', 10, 2)->default(0);
            $table->decimal('passenger_refunds', 10, 2)->default(0);
            $table->decimal('expense_reimbursements', 10, 2)->default(0);
            $table->decimal('tolls', 10, 2)->default(0);
            $table->decimal('campaign_earnings', 10, 2)->default(0);
            $table->decimal('vat_app', 10, 2)->default(0);
            $table->decimal('vat_cash', 10, 2)->default(0);
            $table->decimal('vat_cancellation', 10, 2)->default(0);
            $table->decimal('vat_reservation', 10, 2)->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['uber_driver_identifier', 'week_start']);
            $table->unique(['uber_driver_uuid', 'uber_individual_uuid', 'week_start', 'week_end'], 'uber_driver_week_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uber_driver_earnings');
    }
};
