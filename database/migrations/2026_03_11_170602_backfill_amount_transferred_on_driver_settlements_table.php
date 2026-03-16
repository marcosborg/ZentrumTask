<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('driver_settlements') || ! Schema::hasTable('driver_balance_movements')) {
            return;
        }

        if (! Schema::hasColumn('driver_settlements', 'amount_transferred')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                UPDATE driver_settlements
                SET amount_transferred = COALESCE((
                    SELECT ROUND(SUM(ABS(amount)), 2)
                    FROM driver_balance_movements
                    WHERE type = 'payment'
                      AND driver_settlement_id = driver_settlements.id
                ), 0)
                WHERE is_paid = 1
                  AND amount_transferred = 0
            SQL);

            return;
        }

        DB::statement(<<<'SQL'
            UPDATE driver_settlements ds
            LEFT JOIN (
                SELECT
                    driver_settlement_id,
                    ROUND(SUM(ABS(amount)), 2) AS total_transferred
                FROM driver_balance_movements
                WHERE type = 'payment'
                  AND driver_settlement_id IS NOT NULL
                GROUP BY driver_settlement_id
            ) m ON m.driver_settlement_id = ds.id
            SET ds.amount_transferred = COALESCE(m.total_transferred, 0)
            WHERE ds.is_paid = 1
              AND ds.amount_transferred = 0
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: backfill migration should not rollback data.
    }
};
