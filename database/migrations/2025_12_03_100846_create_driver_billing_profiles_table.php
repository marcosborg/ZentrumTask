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
        Schema::create('driver_billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('taxpayer_type')->default('self_employed');
            $table->boolean('apply_withholding_tax')->default(false);
            $table->decimal('withholding_tax_percent', 5, 2)->nullable();
            $table->decimal('vat_percent', 5, 2)->default(23.00);
            $table->string('vat_refund_mode')->default('none');
            $table->decimal('percent_company', 5, 2)->default(40.00);
            $table->decimal('percent_driver', 5, 2)->default(60.00);
            $table->boolean('tips_to_driver')->default(true);
            $table->string('vehicle_rent_type')->default('none');
            $table->decimal('vehicle_rent_value', 10, 2)->nullable();
            $table->decimal('additional_fixed_fee', 10, 2)->nullable();
            $table->decimal('additional_percent_fee', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_billing_profiles');
    }
};
