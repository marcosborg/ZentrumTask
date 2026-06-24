<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeslaVehicleError extends Model
{
    protected $fillable = [
        'tesla_vehicle_id',
        'source',
        'code',
        'message',
        'occurred_at',
        'raw_payload',
    ];

    public function teslaVehicle(): BelongsTo
    {
        return $this->belongsTo(TeslaVehicle::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }
}
