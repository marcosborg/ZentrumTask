<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_applications', function (Blueprint $table): void {
            $table->string('reservation_payment_provider')->nullable()->after('vehicle_type_id');
            $table->string('reservation_payment_status')->nullable()->after('reservation_payment_provider');
            $table->string('reservation_payment_order_id', 25)->nullable()->unique()->after('reservation_payment_status');
            $table->string('reservation_payment_entity', 10)->nullable()->after('reservation_payment_order_id');
            $table->string('reservation_payment_sub_entity', 10)->nullable()->after('reservation_payment_entity');
            $table->string('reservation_payment_reference', 32)->nullable()->after('reservation_payment_sub_entity');
            $table->string('reservation_payment_request_id')->nullable()->after('reservation_payment_reference');
            $table->decimal('reservation_payment_base_amount', 10, 2)->nullable()->after('reservation_payment_request_id');
            $table->decimal('reservation_payment_vat_rate', 5, 2)->nullable()->after('reservation_payment_base_amount');
            $table->decimal('reservation_payment_amount', 10, 2)->nullable()->after('reservation_payment_vat_rate');
            $table->timestamp('reservation_payment_generated_at')->nullable()->after('reservation_payment_amount');
            $table->timestamp('reservation_payment_expires_at')->nullable()->after('reservation_payment_generated_at');
            $table->timestamp('reservation_payment_paid_at')->nullable()->after('reservation_payment_expires_at');
            $table->timestamp('reservation_payment_last_checked_at')->nullable()->after('reservation_payment_paid_at');
            $table->json('reservation_payment_payload')->nullable()->after('reservation_payment_last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_applications', function (Blueprint $table): void {
            $table->dropUnique(['reservation_payment_order_id']);
            $table->dropColumn([
                'reservation_payment_provider',
                'reservation_payment_status',
                'reservation_payment_order_id',
                'reservation_payment_entity',
                'reservation_payment_sub_entity',
                'reservation_payment_reference',
                'reservation_payment_request_id',
                'reservation_payment_base_amount',
                'reservation_payment_vat_rate',
                'reservation_payment_amount',
                'reservation_payment_generated_at',
                'reservation_payment_expires_at',
                'reservation_payment_paid_at',
                'reservation_payment_last_checked_at',
                'reservation_payment_payload',
            ]);
        });
    }
};
