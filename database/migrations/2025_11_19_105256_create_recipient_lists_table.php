<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recipient_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contact_recipient_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_list_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_id', 'recipient_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_recipient_list');
        Schema::dropIfExists('recipient_lists');
    }
};
