<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeslaChargingEvent extends Model
{
    protected $fillable = [
        'tesla_vehicle_id',
        'source',
        'external_id',
        'started_at',
        'ended_at',
        'energy_kwh',
        'cost',
        'currency',
        'location_name',
        'country',
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
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'energy_kwh' => 'decimal:3',
            'cost' => 'decimal:2',
            'raw_payload' => 'array',
        ];
    }
}
