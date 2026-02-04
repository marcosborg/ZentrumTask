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
        Schema::table('driver_settlements', function (Blueprint $table) {
            $table->decimal('carry_over_balance', 10, 2)->default(0)->after('expenses_total');
            $table->decimal('amount_due', 10, 2)->default(0)->after('amount_payable');
            $table->boolean('is_paid')->default(false)->after('amount_due');
            $table->timestamp('paid_at')->nullable()->after('is_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_settlements', function (Blueprint $table) {
            $table->dropColumn(['carry_over_balance', 'amount_due', 'is_paid', 'paid_at']);
        });
    }
};
