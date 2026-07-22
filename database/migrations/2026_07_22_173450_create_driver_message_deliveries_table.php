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
        Schema::create('driver_message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_message_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('driver_name');
            $table->string('email_address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email_status', 20)->default('pending');
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();
            $table->string('whatsapp_status', 20)->default('pending');
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->foreignId('whatsapp_sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver_message_campaign_id', 'email_status'], 'driver_message_campaign_email_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_message_deliveries');
    }
};
