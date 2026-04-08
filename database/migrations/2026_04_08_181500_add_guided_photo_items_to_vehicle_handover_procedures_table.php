<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_handover_procedures', function (Blueprint $table): void {
            $table->json('guided_photo_items')->nullable()->after('general_photo_paths');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_handover_procedures', function (Blueprint $table): void {
            $table->dropColumn('guided_photo_items');
        });
    }
};
