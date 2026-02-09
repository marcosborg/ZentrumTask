<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementEmailLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_settlement_id',
        'driver_id',
        'triggered_by_user_id',
        'recipient',
        'status',
        'message_id',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver_settlement_id' => 'integer',
            'driver_id' => 'integer',
            'triggered_by_user_id' => 'integer',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(DriverSettlement::class, 'driver_settlement_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
