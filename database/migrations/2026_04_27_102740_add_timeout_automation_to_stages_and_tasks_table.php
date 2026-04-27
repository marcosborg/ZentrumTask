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
        Schema::table('stages', function (Blueprint $table) {
            $table->unsignedInteger('timeout_days')->nullable()->after('freeze_sla');
            $table->foreignId('timeout_target_stage_id')
                ->nullable()
                ->after('timeout_days')
                ->constrained('stages')
                ->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dateTime('stage_entered_at')->nullable()->after('first_interaction_at');
            $table->index('stage_entered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['stage_entered_at']);
            $table->dropColumn('stage_entered_at');
        });

        Schema::table('stages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('timeout_target_stage_id');
            $table->dropColumn('timeout_days');
        });
    }
};
