<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverBalance extends Model
{
    /** @use HasFactory<\Database\Factories\DriverBalanceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'current_balance',
        'last_settlement_id',
        'is_settled',
        'settled_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'is_settled' => 'boolean',
            'settled_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function lastSettlement(): BelongsTo
    {
        return $this->belongsTo(DriverSettlement::class, 'last_settlement_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(DriverBalanceMovement::class);
    }
}
