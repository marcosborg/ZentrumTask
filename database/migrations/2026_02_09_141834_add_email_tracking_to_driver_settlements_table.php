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
            if (! Schema::hasColumn('driver_settlements', 'email_sent_count')) {
                $table->unsignedInteger('email_sent_count')->default(0)->after('is_paid');
            }

            if (! Schema::hasColumn('driver_settlements', 'last_emailed_at')) {
                $table->timestamp('last_emailed_at')->nullable()->after('email_sent_count');
            }

            if (! Schema::hasColumn('driver_settlements', 'last_emailed_to')) {
                $table->string('last_emailed_to')->nullable()->after('last_emailed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_settlements', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('driver_settlements', 'last_emailed_to')) {
                $columns[] = 'last_emailed_to';
            }

            if (Schema::hasColumn('driver_settlements', 'last_emailed_at')) {
                $columns[] = 'last_emailed_at';
            }

            if (Schema::hasColumn('driver_settlements', 'email_sent_count')) {
                $columns[] = 'email_sent_count';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
