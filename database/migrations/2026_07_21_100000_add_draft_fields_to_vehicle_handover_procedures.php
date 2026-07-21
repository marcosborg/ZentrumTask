<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_handover_procedures', function (Blueprint $table): void {
            $table->string('draft_step', 32)->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('performed_at');
            $table->timestamp('last_synced_at')->nullable()->after('completed_at');
            $table->index(['operator_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_handover_procedures', function (Blueprint $table): void {
            $table->dropIndex(['operator_user_id', 'status']);
            $table->dropColumn(['draft_step', 'completed_at', 'last_synced_at']);
        });
    }
};
