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
        Schema::table('tesla_accounts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner_email')->nullable();
            $table->json('scopes')->nullable();
            $table->index('owner_email');
        });

        Schema::table('tesla_vehicles', function (Blueprint $table) {
            $table->string('model')->nullable();
            $table->decimal('odometer', 12, 2)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->json('raw_payload')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tesla_vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'model',
                'odometer',
                'battery_level',
                'raw_payload',
            ]);
        });

        Schema::table('tesla_accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['owner_email']);
            $table->dropColumn([
                'user_id',
                'owner_email',
                'scopes',
            ]);
        });
    }
};
