<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vehicle extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\VehicleFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $fillable = [
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

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
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

    public function publicSlug(): string
    {
        return Str::slug($this->displayName().' '.$this->license_plate) ?: 'viatura-'.$this->getKey();
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

    /**
     * @return list<string>
     */
    public function galleryImageUrls(): array
    {
        $websitePhotos = $this->relationLoaded('websitePhotos')
            ? $this->websitePhotos
            : $this->websitePhotos()->get();

        if ($websitePhotos->isNotEmpty()) {
            return $websitePhotos
                ->pluck('photo_path')
                ->filter()
                ->map(fn (string $path): string => asset('storage/'.$path))
                ->values()
                ->all();
        }

        return collect($this->getMedia('vehicle_photos'))
            ->map(fn ($media): string => route('media.proxy', [
                'uuid' => $media->uuid,
            ]))
            ->values()
            ->all();
    }

    public function primaryImageUrl(): string
    {
        return $this->galleryImageUrls()[0] ?? asset('website/assets/car_sedan.png');
    }
}
