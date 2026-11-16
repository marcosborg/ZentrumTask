<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_driver_balances', function (Blueprint $table): void {
            $table->enum('platform', ['bolt', 'uber', 'pop'])->change();
        });
        Schema::table('drivers', function (Blueprint $table): void {
            $table->string('pop_driver_name')->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('platform_driver_balances')->where('platform', 'pop')->exists()) {
            throw new RuntimeException('Existem saldos POP. Reconcilie-os antes de reverter a migração.');
        }
        Schema::table('platform_driver_balances', function (Blueprint $table): void {
            $table->enum('platform', ['bolt', 'uber'])->change();
        });
        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropUnique(['pop_driver_name']);
            $table->dropColumn('pop_driver_name');
        });
    }
};
