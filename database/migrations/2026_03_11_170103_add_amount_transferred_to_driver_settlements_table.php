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
        Schema::table('driver_settlements', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_settlements', 'amount_transferred')) {
                $table->decimal('amount_transferred', 10, 2)->default(0)->after('amount_due');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_settlements', function (Blueprint $table) {
            if (Schema::hasColumn('driver_settlements', 'amount_transferred')) {
                $table->dropColumn('amount_transferred');
            }
        });
    }
};
