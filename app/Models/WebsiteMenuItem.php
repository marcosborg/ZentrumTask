<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class WebsiteMenuItem extends Model
{
    /** @use HasFactory<\Database\Factories\WebsiteMenuItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'label',
        'url',
        'position',
        'parent_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if ($item->position) {
                return;
            }

            $nextPosition = (int) DB::table('website_menu_items')->max('position');

            $item->position = $nextPosition + 1;
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }
}
