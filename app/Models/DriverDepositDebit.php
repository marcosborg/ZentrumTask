<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverDepositDebit extends Model
{
    /** @use HasFactory<\Database\Factories\DriverDepositDebitFactory> */
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'driver_settlement_id',
        'created_by_user_id',
        'occurred_at',
        'amount',
        'description',
        'notes',
        'source_file',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(DriverSettlement::class, 'driver_settlement_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'date',
            'amount' => 'decimal:2',
        ];
    }
}
