<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vehicle extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\VehicleFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $fillable = [
        'operation',
        'owner_driver_id',
        'license_plate',
        'prio_card_code',
        'prio_card_label',
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
        'source',
        'acquisition_date',
        'acquisition_cost',
        'weekly_rental_price',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $vehicle): void {
            if ($vehicle->operation === 'slot' && ! $vehicle->owner_driver_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['owner_driver_id' => 'Identifique o motorista proprietário.']);
            }
            if ($vehicle->exists && $vehicle->isDirty(['operation', 'owner_driver_id']) && $vehicle->allocations()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['operation' => 'Preserve a operação e o proprietário das viaturas com histórico.']);
            }
        });
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Driver::class, 'owner_driver_id');
    }

    public function checkups(): HasMany
    {
        return $this->hasMany(VehicleCheckup::class);
    }

    public function scopeForOperation(Builder $query, \App\Enums\TvdeOperation $operation): Builder
    {
        return $query->where('operation', $operation->value);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VehicleAllocation::class);
    }

    public function prioTransactions(): HasMany
    {
        return $this->hasMany(PrioTransaction::class);
    }

    public function currentAllocation(): HasOne
    {
        return $this->hasOne(VehicleAllocation::class)
            ->whereIn('status', ['active', 'closed'])
            ->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
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
            'source' => 'string',
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'weekly_rental_price' => 'decimal:2',
        ];
    }

    public function websitePhotos(): HasMany
    {
        return $this->hasMany(VehicleWebsitePhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('vehicle_photos')
            ->useDisk('public');
    }

    public function scopeWebsiteCatalog(Builder $query): Builder
    {
        return $query
            ->where('source', 'tvde')
            ->where('operation', 'rental')
            ->orderByRaw("CASE WHEN status = 'available' THEN 0 ELSE 1 END")
            ->orderBy('make')
            ->orderBy('model')
            ->orderBy('license_plate');
    }

    public function scopeWebsiteAvailable(Builder $query): Builder
    {
        return $query
            ->websiteCatalog()
            ->where('status', 'available');
    }

    public function displayName(): string
    {
        return trim((string) collect([$this->make, $this->model, $this->trim])->filter()->implode(' '));
    }

    public function maskedVin(): ?string
    {
        $vin = strtoupper(trim((string) $this->vin));

        if ($vin === '') {
            return null;
        }

        if (strlen($vin) <= 4) {
            return 'XXXX';
        }

        return substr($vin, 0, -4).'XXXX';
    }

    public function publicSlug(): string
    {
        return Str::slug($this->displayName()) ?: 'viatura-'.$this->getKey();
    }

    public function publicUrl(): string
    {
        return route('vehicle.show', [
            'vehicle' => $this,
            'slug' => $this->publicSlug(),
        ]);
    }

    public function websiteAvailabilityLabel(): string
    {
        return $this->status === 'available' ? 'Disponivel' : 'Indisponivel';
    }

    public function websiteAvailabilityColor(): string
    {
        return $this->status === 'available' ? 'success' : 'danger';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'available' => 'Disponivel',
            'allocated' => 'Alocada',
            'maintenance' => 'Manutencao',
            'accident' => 'Acidente',
            'sold' => 'Vendida',
            'inactive' => 'Inativa',
            default => (string) $this->status,
        };
    }

    public function hasWeeklyRentalPrice(): bool
    {
        return (float) ($this->weekly_rental_price ?? 0) > 0;
    }

    public function weeklyRentalPriceFormatted(): string
    {
        return number_format((float) ($this->weekly_rental_price ?? 0), 2, ',', ' ');
    }

    /**
     * @return list<string>
     */
    public function galleryImageUrls(): array
    {
        $websitePhotos = $this->relationLoaded('websitePhotos')
            ? $this->websitePhotos
            : $this->websitePhotos()->get();

        return $websitePhotos
            ->pluck('photo_path')
            ->filter()
            ->map(fn (string $path): string => Storage::disk('public')->url($path))
            ->values()
            ->all();
    }

    public function primaryImageUrl(): string
    {
        return $this->galleryImageUrls()[0] ?? asset('website/assets/car_sedan.png');
    }
}
