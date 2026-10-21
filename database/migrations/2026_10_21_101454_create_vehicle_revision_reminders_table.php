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
        Schema::create('vehicle_revision_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('cycle', 64);
            $table->date('sent_on');
            $table->timestamps();
            $table->unique(['vehicle_revision_id', 'user_id', 'cycle', 'sent_on'], 'revision_reminder_daily_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_revision_reminders');
    }
};
