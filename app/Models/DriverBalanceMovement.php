<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverBalanceMovement extends Model
{
    use \App\Models\Concerns\BelongsToParticipation;

    /** @use HasFactory<\Database\Factories\DriverBalanceMovementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_reference',
        'driver_participation_id',
        'operation',
        'driver_id',
        'driver_balance_id',
        'driver_settlement_id',
        'amount',
        'type',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(DriverBalance::class, 'driver_balance_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(DriverSettlement::class, 'driver_settlement_id');
    }
}
