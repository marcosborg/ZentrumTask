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
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'prio_card_code')) {
                $table->string('prio_card_code')->nullable()->after('license_plate')->index();
            }

            if (! Schema::hasColumn('vehicles', 'prio_card_label')) {
                $table->string('prio_card_label')->nullable()->after('prio_card_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [];

        if (Schema::hasColumn('vehicles', 'prio_card_code')) {
            $columns[] = 'prio_card_code';
        }

        if (Schema::hasColumn('vehicles', 'prio_card_label')) {
            $columns[] = 'prio_card_label';
        }

        if ($columns !== []) {
            Schema::table('vehicles', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
