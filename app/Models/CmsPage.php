<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CmsPage extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\CmsPageFactory> */
    use HasFactory;

    use InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'highlight',
        'body',
        'is_active',
        'meta_title',
        'meta_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getSlugAttribute(): string
    {
        return Str::slug($this->title);
    }

    public function publicUrl(): string
    {
        return 'https://zentrum-tvde.com/cms/'.$this->getKey().'/'.$this->slug;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->singleFile()
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('featured_cover')
            ->performOnCollections('featured_image')
            ->fit(Fit::Crop, 1600, 900)
            ->nonQueued();

        $this->addMediaConversion('featured_thumb')
            ->performOnCollections('featured_image')
            ->fit(Fit::Crop, 800, 450)
            ->nonQueued();
    }
}
