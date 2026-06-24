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
        Schema::table('tesla_vehicle_snapshots', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('recorded_at')->index();
            $table->foreignId('vehicle_weekly_mileage_id')->nullable()->after('is_manual')->constrained()->nullOnDelete();
            $table->string('locality')->nullable()->after('longitude');
            $table->string('formatted_address')->nullable()->after('locality');
            $table->string('google_place_id')->nullable()->after('formatted_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tesla_vehicle_snapshots', function (Blueprint $table) {
            $table->dropForeign(['vehicle_weekly_mileage_id']);
            $table->dropColumn([
                'is_manual',
                'vehicle_weekly_mileage_id',
                'locality',
                'formatted_address',
                'google_place_id',
            ]);
        });
    }
};
