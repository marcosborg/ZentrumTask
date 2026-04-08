<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_handover_procedures', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('status', 20)->default('completed');
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('closed_allocation_id')->nullable()->constrained('vehicle_allocations')->nullOnDelete();
            $table->foreignId('created_allocation_id')->nullable()->constrained('vehicle_allocations')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->date('allocation_effective_start_date')->nullable();
            $table->date('allocation_effective_end_date')->nullable();
            $table->json('vehicle_snapshot')->nullable();
            $table->json('driver_snapshot')->nullable();
            $table->json('checklist_payload');
            $table->json('damage_items')->nullable();
            $table->json('general_photo_paths')->nullable();
            $table->boolean('battery_minimum_confirmed')->default(false);
            $table->unsignedTinyInteger('battery_minimum_percent')->nullable();
            $table->boolean('deposit_paid_confirmed')->default(false);
            $table->decimal('deposit_paid_amount', 10, 2)->nullable();
            $table->longText('notes')->nullable();
            $table->longText('operator_signature_data_url');
            $table->longText('driver_signature_data_url');
            $table->longText('html_snapshot')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index(['type', 'performed_at']);
            $table->index(['vehicle_id', 'performed_at']);
            $table->index(['driver_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_handover_procedures');
    }
};
