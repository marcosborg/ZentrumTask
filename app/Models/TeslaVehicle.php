<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TeslaVehicle extends Model
{
    /** @use HasFactory<\Database\Factories\TeslaVehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'tesla_account_id',
        'vehicle_id',
        'tesla_vehicle_id',
        'vin',
        'display_name',
        'state',
        'model',
        'odometer',
        'battery_level',
        'raw_payload',
        'last_seen_at',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(TeslaAccount::class, 'tesla_account_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(TeslaVehicleSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(TeslaVehicleSnapshot::class)->latestOfMany('recorded_at');
    }

    public function chargingEvents(): HasMany
    {
        return $this->hasMany(TeslaChargingEvent::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(TeslaVehicleError::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'odometer' => 'decimal:2',
            'battery_level' => 'integer',
            'raw_payload' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }
}
