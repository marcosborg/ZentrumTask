<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleWeeklyMileage extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleWeeklyMileageFactory> */
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'period_start',
        'period_end',
        'weekly_km',
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
            'period_start' => 'date',
            'period_end' => 'date',
            'weekly_km' => 'decimal:2',
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
