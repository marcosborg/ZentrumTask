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
            if (! Schema::hasColumn('platform_driver_balances', 'net_source_column')) {
                $table->string('net_source_column')->nullable()->after('tips_amount');
            }

            if (! Schema::hasColumn('platform_driver_balances', 'tips_source_column')) {
                $table->string('tips_source_column')->nullable()->after('net_source_column');
            }

            if (! Schema::hasColumn('platform_driver_balances', 'raw_row')) {
                $table->json('raw_row')->nullable()->after('tips_source_column');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [];

        if (Schema::hasColumn('platform_driver_balances', 'net_source_column')) {
            $columns[] = 'net_source_column';
        }

        if (Schema::hasColumn('platform_driver_balances', 'tips_source_column')) {
            $columns[] = 'tips_source_column';
        }

        if (Schema::hasColumn('platform_driver_balances', 'raw_row')) {
            $columns[] = 'raw_row';
        }

        if ($columns !== []) {
            Schema::table('platform_driver_balances', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
