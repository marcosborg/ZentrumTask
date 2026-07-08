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
        Schema::table('vehicle_handover_procedures', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicle_handover_procedures', 'fault_items')) {
                $table->json('fault_items')->nullable()->after('damage_items');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_handover_procedures', function (Blueprint $table): void {
            if (Schema::hasColumn('vehicle_handover_procedures', 'fault_items')) {
                $table->dropColumn('fault_items');
            }
        });
    }
};
