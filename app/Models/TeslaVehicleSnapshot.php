<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeslaVehicleSnapshot extends Model
{
    protected $fillable = [
        'tesla_vehicle_id',
        'recorded_at',
        'vehicle_state',
        'charging_state',
        'battery_level',
        'usable_battery_level',
        'battery_range',
        'est_battery_range',
        'rated_battery_range',
        'odometer',
        'speed',
        'latitude',
        'longitude',
        'heading',
        'shift_state',
        'charge_energy_added',
        'charger_power',
        'charge_limit_soc',
        'inside_temp',
        'outside_temp',
        'driver_temp_setting',
        'passenger_temp_setting',
        'tpms_pressure_fl',
        'tpms_pressure_fr',
        'tpms_pressure_rl',
        'tpms_pressure_rr',
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
            'recorded_at' => 'datetime',
            'battery_level' => 'integer',
            'usable_battery_level' => 'integer',
            'battery_range' => 'decimal:2',
            'est_battery_range' => 'decimal:2',
            'rated_battery_range' => 'decimal:2',
            'odometer' => 'decimal:2',
            'speed' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'heading' => 'integer',
            'charge_energy_added' => 'decimal:2',
            'charger_power' => 'decimal:2',
            'charge_limit_soc' => 'integer',
            'inside_temp' => 'decimal:2',
            'outside_temp' => 'decimal:2',
            'driver_temp_setting' => 'decimal:2',
            'passenger_temp_setting' => 'decimal:2',
            'tpms_pressure_fl' => 'decimal:2',
            'tpms_pressure_fr' => 'decimal:2',
            'tpms_pressure_rl' => 'decimal:2',
            'tpms_pressure_rr' => 'decimal:2',
            'raw_payload' => 'array',
        ];
    }
}
