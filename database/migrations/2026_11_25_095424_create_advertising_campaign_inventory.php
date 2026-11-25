<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_inventory', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->unique()->constrained()->restrictOnDelete();
            $table->string('region')->nullable();
            $table->boolean('eligible')->default(false);
            $table->timestamp('interested_at')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->string('availability')->default('under_review');
            $table->json('formats')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });
        Schema::create('advertising_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
            $table->foreignId('advertising_format_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('draft');
            foreach (['contract_value', 'production_cost', 'installation_cost', 'removal_cost', 'driver_compensation'] as $column) {
                $table->decimal($column, 12, 2)->nullable();
            }
            $table->json('documents')->nullable();
            $table->json('photos')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('advertising_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained('advertising_inventory')->restrictOnDelete();
            $table->enum('space', ['rear_doors', 'front_doors', 'rear_fenders', 'interior']);
            $table->date('reserved_on');
            $table->unique(['inventory_id', 'space', 'reserved_on'], 'ads_unique_space_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_reservations');
        Schema::dropIfExists('advertising_campaigns');
        Schema::dropIfExists('advertising_inventory');
    }
};
