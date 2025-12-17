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
        Schema::table('notification_rules', function (Blueprint $table) {
            $table->boolean('send_to_task_email')
                ->default(false)
                ->after('also_send_to_assigned_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_rules', function (Blueprint $table) {
            $table->dropColumn('send_to_task_email');
        });
    }
};
