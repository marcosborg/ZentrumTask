<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vehicle extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\VehicleFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $fillable = [
        'license_plate',
        'vin',
        'make',
        'model',
        'trim',
        'year',
        'fuel_type',
        'transmission',
        'color',
        'seats',
        'engine_cc',
        'power_kw',
        'current_odometer',
        'status',
        'is_tvde',
        'acquisition_date',
        'acquisition_cost',
        'notes',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VehicleAllocation::class);
    }

    public function currentAllocation(): HasOne
    {
        return $this->hasOne(VehicleAllocation::class)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->latest('starts_at');
    }

    protected function currentDriver(): Attribute
    {
        return Attribute::get(fn (): ?Driver => $this->currentAllocation?->driver);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'seats' => 'integer',
            'engine_cc' => 'integer',
            'power_kw' => 'integer',
            'current_odometer' => 'integer',
            'is_tvde' => 'boolean',
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('vehicle_photos')
            ->useDisk('public');
    }
}
