<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoltDriverEarning extends Model
{
    /** @use HasFactory<\Database\Factories\BoltDriverEarningFactory> */
    use HasFactory;

    protected $fillable = [
        'bolt_sync_run_id',
        'driver_id',
        'bolt_driver_identifier',
        'bolt_driver_name',
        'bolt_driver_email',
        'bolt_driver_uuid',
        'bolt_individual_uuid',
        'driver_name_snapshot',
        'driver_email_snapshot',
        'driver_resolved',
        'week_start',
        'week_end',
        'total_amount',
        'currency',
        'gross_total',
        'gross_app',
        'gross_cash',
        'net_total',
        'expected_payment',
        'cash_collected',
        'tips',
        'commissions',
        'total_fees',
        'reservation_fees',
        'other_fees',
        'passenger_refunds',
        'expense_reimbursements',
        'tolls',
        'campaign_earnings',
        'vat_app',
        'vat_cash',
        'vat_cancellation',
        'vat_reservation',
        'raw_payload',
    ];

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(BoltSyncRun::class, 'bolt_sync_run_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'total_amount' => 'decimal:2',
            'driver_resolved' => 'boolean',
            'gross_total' => 'decimal:2',
            'gross_app' => 'decimal:2',
            'gross_cash' => 'decimal:2',
            'net_total' => 'decimal:2',
            'expected_payment' => 'decimal:2',
            'cash_collected' => 'decimal:2',
            'tips' => 'decimal:2',
            'commissions' => 'decimal:2',
            'total_fees' => 'decimal:2',
            'reservation_fees' => 'decimal:2',
            'other_fees' => 'decimal:2',
            'passenger_refunds' => 'decimal:2',
            'expense_reimbursements' => 'decimal:2',
            'tolls' => 'decimal:2',
            'campaign_earnings' => 'decimal:2',
            'vat_app' => 'decimal:2',
            'vat_cash' => 'decimal:2',
            'vat_cancellation' => 'decimal:2',
            'vat_reservation' => 'decimal:2',
            'raw_payload' => 'array',
        ];
    }
}
