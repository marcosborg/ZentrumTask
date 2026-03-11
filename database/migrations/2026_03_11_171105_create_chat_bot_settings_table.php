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
        Schema::create('chat_bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Zentrum Assistant');
            $table->boolean('is_enabled')->default(true);
            $table->text('welcome_message')->nullable();
            $table->longText('system_instructions')->nullable();
            $table->string('model')->default('gpt-4.1-mini');
            $table->decimal('temperature', 3, 2)->default(0.30);
            $table->unsignedSmallInteger('max_history_messages')->default(20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_bot_settings');
    }
};
