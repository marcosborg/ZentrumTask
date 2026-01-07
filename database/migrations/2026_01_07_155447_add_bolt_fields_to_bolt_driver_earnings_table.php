<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bolt_driver_earnings', function (Blueprint $table) {
            $table->string('bolt_driver_uuid')->nullable()->index();
            $table->string('bolt_individual_uuid')->nullable()->index();
            $table->string('driver_name_snapshot')->nullable();
            $table->string('driver_email_snapshot')->nullable();
            $table->boolean('driver_resolved')->default(true)->index();
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
        });

        Schema::table('bolt_driver_earnings', function (Blueprint $table) {
            $table->unique(['bolt_driver_uuid', 'bolt_individual_uuid', 'week_start', 'week_end'], 'bolt_driver_week_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bolt_driver_earnings', function (Blueprint $table) {
            $table->dropUnique('bolt_driver_week_unique');
            $table->dropColumn([
                'bolt_driver_uuid',
                'bolt_individual_uuid',
                'driver_name_snapshot',
                'driver_email_snapshot',
                'driver_resolved',
                'gross_total',
                'gross_app',
                'gross_cash',
                'net_total',
                'expected_payment',
                'cash_collected',
                'tips',
                'commissions',
                'total_fees',
                'reservation_fees',
                'other_fees',
                'passenger_refunds',
                'expense_reimbursements',
                'tolls',
                'campaign_earnings',
                'vat_app',
                'vat_cash',
                'vat_cancellation',
                'vat_reservation',
            ]);
        });
    }
};
