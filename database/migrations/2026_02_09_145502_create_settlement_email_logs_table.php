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
        Schema::create('settlement_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_settlement_id')->constrained('driver_settlements')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient');
            $table->string('status');
            $table->string('message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['driver_settlement_id', 'created_at'], 'settlement_email_logs_settlement_created_idx');
            $table->index(['status', 'created_at'], 'settlement_email_logs_status_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_email_logs');
    }
};
