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
        Schema::create('tesla_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('tesla_user_id')->nullable()->unique();
            $table->string('email')->nullable();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tesla_accounts');
    }
};
