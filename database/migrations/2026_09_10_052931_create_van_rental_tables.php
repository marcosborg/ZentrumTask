<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_vans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('license_plate')->unique();
            $table->string('status')->default('draft')->index();
            $table->boolean('featured')->default(false);
            $table->text('description')->nullable();
            $table->json('photos')->nullable();
            $table->json('equipment')->nullable();
            $table->decimal('volume_m3', 6, 2)->nullable();
            $table->unsignedInteger('payload_kg')->nullable();
            $table->string('cargo_dimensions')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->string('fuel')->nullable();
            $table->string('transmission')->nullable();
            $table->boolean('self_drive')->default(true);
            $table->boolean('with_driver')->default(false);
            $table->unsignedInteger('self_drive_rate')->nullable();
            $table->unsignedInteger('with_driver_rate')->nullable();
            $table->unsignedInteger('deposit')->default(0);
            $table->unsignedSmallInteger('minimum_hours')->default(1);
            $table->unsignedSmallInteger('buffer_minutes')->default(30);
            $table->unsignedSmallInteger('lead_hours')->default(2);
            $table->time('opens_at')->default('08:00');
            $table->time('closes_at')->default('20:00');
            $table->string('pickup_location')->nullable();
            $table->text('mileage_terms')->nullable();
            $table->text('fuel_terms')->nullable();
            $table->text('cancellation_terms')->nullable();
            $table->text('rental_terms')->nullable();
            $table->timestamps();
        });
        Schema::create('van_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_van_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->uuid('submission_key')->unique();
            $table->string('status')->default('pending');
            $table->string('mode');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('buffer_minutes');
            $table->unsignedInteger('hourly_rate');
            $table->unsignedInteger('billable_hours');
            $table->unsignedInteger('estimated_total');
            $table->unsignedInteger('deposit');
            $table->json('terms');
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('purpose');
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->boolean('loading_help')->default(false);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('driver_name')->nullable();
            $table->boolean('driver_verified')->default(false);
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->text('kanban_error')->nullable();
            $table->timestamps();
            $table->index(['rental_van_id', 'status', 'starts_at', 'ends_at'], 'van_reservation_availability');
        });
        Schema::create('van_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_van_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['rental_van_id', 'starts_at', 'ends_at']);
        });
        Schema::create('van_reservation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('van_reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason');
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('van_reservation_events');
        Schema::dropIfExists('van_blocks');
        Schema::dropIfExists('van_reservations');
        Schema::dropIfExists('rental_vans');
    }
};
