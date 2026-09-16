<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RentalVan extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['photos' => 'array', 'equipment' => 'array', 'featured' => 'boolean', 'self_drive' => 'boolean', 'with_driver' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $van): void {
            foreach ($van->photos ?? [] as $photo) {
                if (! is_string($photo) || ! preg_match('/\Arental-vans\/[A-Za-z0-9_-]+\.(jpe?g|png|webp|gif)\z/i', $photo)) {
                    throw ValidationException::withMessages(['data.photos' => 'Utilize fotografias carregadas nesta área.']);
                }
            }
            if ($van->status !== 'published') {
                return;
            }
            $errors = [];
            foreach (['description', 'photos', 'volume_m3', 'payload_kg', 'cargo_dimensions', 'pickup_location', 'mileage_terms', 'fuel_terms', 'cancellation_terms', 'rental_terms'] as $field) {
                if (blank($van->$field)) {
                    $errors['data.'.$field] = 'Obrigatório para publicar a carrinha.';
                }
            }
            if (! $van->self_drive && ! $van->with_driver) {
                $errors['data.self_drive'] = 'Ative pelo menos uma modalidade.';
            }
            foreach (['self_drive', 'with_driver'] as $mode) {
                if ($van->$mode && (int) $van->{$mode.'_rate'} <= 0) {
                    $errors['data.'.$mode.'_rate'] = 'Indique uma tarifa superior a zero.';
                }
            }
            if ($van->opens_at >= $van->closes_at) {
                $errors['data.closes_at'] = 'O horário de fecho deve ser posterior à abertura.';
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        });
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(VanReservation::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(VanBlock::class);
    }

    public function publicUrl(): string
    {
        return route('van-rentals.show', ['van' => $this, 'slug' => Str::slug($this->name)]);
    }

    public function imageUrl(): ?string
    {
        return empty($this->photos) ? null : Storage::disk('rental_vans')->url($this->photos[0]);
    }

    public function startingRate(): int
    {
        $rates = [];
        foreach (['self_drive', 'with_driver'] as $mode) {
            if ($this->$mode && $this->{$mode.'_rate'} > 0) {
                $rates[] = (int) $this->{$mode.'_rate'};
            }
        }

        return $rates ? min($rates) : 0;
    }

    public function startingPricingUnit(): string
    {
        $rates = [];
        foreach (['self_drive', 'with_driver'] as $mode) {
            if ($this->$mode && $this->{$mode.'_rate'} > 0) {
                $rates[$mode] = (int) $this->{$mode.'_rate'};
            }
        }
        asort($rates);
        $mode = array_key_first($rates);

        return $mode ? ($this->{$mode.'_pricing_unit'} ?? 'hour') : 'hour';
    }
}
