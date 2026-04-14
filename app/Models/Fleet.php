<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Fleet extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'brand',
        'model',
        'rental_price',
        'price_suffix',
        'excerpt',
        'description',
        'photo_path',
        'gallery_paths',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gallery_paths' => 'array',
            'is_published' => 'boolean',
            'rental_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $fleet): void {
            if (blank($fleet->name)) {
                $fleet->name = trim(collect([$fleet->brand, $fleet->model])->filter()->implode(' '));
            }

            if (blank($fleet->slug)) {
                $fleet->slug = $fleet->generateUniqueSlug($fleet->name);
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function displayName(): string
    {
        return trim((string) ($this->name ?: collect([$this->brand, $this->model])->filter()->implode(' ')));
    }

    public function priceLabel(): ?string
    {
        if ($this->rental_price === null) {
            return null;
        }

        return number_format((float) $this->rental_price, 0, ',', '.').' EUR'.($this->price_suffix ? ' '.$this->price_suffix : '');
    }

    public function primaryImageUrl(): string
    {
        return $this->photo_path
            ? asset('storage/'.$this->photo_path)
            : asset('website/assets/car_sedan.png');
    }

    /**
     * @return list<string>
     */
    public function galleryImageUrls(): array
    {
        return collect($this->gallery_paths ?? [])
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => asset('storage/'.$path))
            ->prepend($this->primaryImageUrl())
            ->unique()
            ->values()
            ->all();
    }

    public function publicUrl(): string
    {
        return route('fleet.show', $this);
    }

    protected function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'viatura';
        $slug = $base;
        $suffix = 1;

        while (self::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
