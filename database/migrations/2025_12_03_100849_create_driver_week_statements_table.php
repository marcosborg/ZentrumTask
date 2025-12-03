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
        Schema::create('driver_week_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_profile_id')->constrained('driver_billing_profiles')->cascadeOnDelete();
            $table->unsignedBigInteger('tvde_week_id')->nullable();
            $table->date('week_start_date')->nullable();
            $table->date('week_end_date')->nullable();
            $table->decimal('gross_total', 12, 2)->default(0);
            $table->decimal('net_total', 12, 2)->default(0);
            $table->decimal('tips_total', 12, 2)->default(0);
            $table->decimal('company_share', 12, 2)->default(0);
            $table->decimal('driver_share', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('withholding_amount', 12, 2)->default(0);
            $table->decimal('expenses_total', 12, 2)->default(0);
            $table->decimal('rent_amount', 12, 2)->default(0);
            $table->decimal('additional_fees_total', 12, 2)->default(0);
            $table->decimal('amount_payable_to_driver', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->dateTime('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_week_statements');
    }
};
