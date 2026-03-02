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
        Schema::table('driver_billing_profiles', function (Blueprint $table) {
            $table->decimal('extra_km_limit', 10, 2)->default(2000)->after('vehicle_rent_value');
            $table->decimal('extra_km_rate', 10, 4)->default(0.12)->after('extra_km_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_billing_profiles', function (Blueprint $table) {
            $table->dropColumn(['extra_km_limit', 'extra_km_rate']);
        });
    }
};
