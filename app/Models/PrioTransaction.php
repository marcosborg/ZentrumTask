<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrioTransaction extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'occurred_at',
        'card_code',
        'vehicle_plate',
        'id_usage',
        'station_id',
        'energy_kwh',
        'net_amount',
        'gross_amount',
        'vat_rate',
        'vehicle_id',
        'driver_id',
        'assignment_status',
        'raw_row',
        'imported_at',
        'source_file',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'energy_kwh' => 'decimal:3',
            'net_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'raw_row' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
