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
        Schema::table('platform_driver_balances', function (Blueprint $table) {
            $table->string('net_source_column')->nullable()->after('tips_amount');
            $table->string('tips_source_column')->nullable()->after('net_source_column');
            $table->json('raw_row')->nullable()->after('tips_source_column');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_driver_balances', function (Blueprint $table) {
            $table->dropColumn(['net_source_column', 'tips_source_column', 'raw_row']);
        });
    }
};
