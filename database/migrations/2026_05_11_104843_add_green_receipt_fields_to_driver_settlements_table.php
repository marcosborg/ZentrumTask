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
            if (! Schema::hasColumn('driver_settlements', 'green_receipt_path')) {
                $table->string('green_receipt_path')->nullable()->after('paid_at');
            }

            if (! Schema::hasColumn('driver_settlements', 'green_receipt_uploaded_at')) {
                $table->timestamp('green_receipt_uploaded_at')->nullable()->after('green_receipt_path');
            }

            if (! Schema::hasColumn('driver_settlements', 'green_receipt_uploaded_by_user_id')) {
                $table->foreignId('green_receipt_uploaded_by_user_id')
                    ->nullable()
                    ->after('green_receipt_uploaded_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_settlements', function (Blueprint $table) {
            if (Schema::hasColumn('driver_settlements', 'green_receipt_uploaded_by_user_id')) {
                $table->dropForeign(['green_receipt_uploaded_by_user_id']);
            }

            $columns = [];

            if (Schema::hasColumn('driver_settlements', 'green_receipt_uploaded_by_user_id')) {
                $columns[] = 'green_receipt_uploaded_by_user_id';
            }

            if (Schema::hasColumn('driver_settlements', 'green_receipt_uploaded_at')) {
                $columns[] = 'green_receipt_uploaded_at';
            }

            if (Schema::hasColumn('driver_settlements', 'green_receipt_path')) {
                $columns[] = 'green_receipt_path';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
