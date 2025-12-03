<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trigger')->default('on_enter_stage'); // futuro: on_leave_stage, on_sla_breached, etc.
            $table->string('send_mode')->default('always'); // always, first_time, cooldown
            $table->unsignedInteger('cooldown_hours')->nullable();
            $table->boolean('also_send_to_assigned_user')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['stage_id', 'trigger', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
