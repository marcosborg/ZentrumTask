<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VehicleAllocation extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleAllocationFactory> */
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'starts_at',
        'ends_at',
        'start_odometer',
        'end_odometer',
        'status',
        'handover_location',
        'notes',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNull('ends_at');
    }

    protected static function booted(): void
    {
        static::saving(function (VehicleAllocation $allocation): void {
            if (! $allocation->starts_at) {
                return;
            }

            $start = $allocation->starts_at instanceof Carbon
                ? $allocation->starts_at
                : Carbon::parse($allocation->starts_at);

            $end = $allocation->ends_at instanceof Carbon
                ? $allocation->ends_at
                : ($allocation->ends_at ? Carbon::parse($allocation->ends_at) : null);

            $query = VehicleAllocation::query()
                ->where('vehicle_id', $allocation->vehicle_id)
                ->when($allocation->getKey(), fn (Builder $query): Builder => $query->whereKeyNot($allocation->getKey()))
                ->where(function (Builder $query) use ($start, $end): void {
                    if ($end) {
                        $query->where('starts_at', '<=', $end);
                    }

                    $query->where(function (Builder $query) use ($start): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', $start);
                    });
                });

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Esta viatura ja esta alocada nesse periodo.',
                ]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'start_odometer' => 'integer',
            'end_odometer' => 'integer',
        ];
    }
}
