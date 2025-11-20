<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('normal'); // low, normal, high, critical
            $table->dateTime('due_at')->nullable();
            $table->unsignedInteger('position')->default(0); // posição dentro do estágio
            $table->string('external_reference')->nullable();
            $table->json('meta')->nullable(); // campo flexível para futuro
            $table->timestamps();
            $table->softDeletes();

            $table->index(['board_id', 'stage_id']);
            $table->index(['priority']);
            $table->index(['due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
