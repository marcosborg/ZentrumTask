<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class VehicleDocument extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\VehicleDocumentFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'type',
        'title',
        'document_number',
        'issuer',
        'issue_date',
        'expires_at',
        'notes',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(VehicleDocumentAlert::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('document')
            ->singleFile()
            ->useDisk('public');
    }

    protected function status(): Attribute
    {
        return Attribute::get(function (): string {
            if (! $this->expires_at) {
                return 'no_expiry';
            }

            $today = Carbon::today();
            $expiresAt = $this->expires_at->copy()->startOfDay();

            if ($expiresAt->lt($today)) {
                return 'expired';
            }

            if ($expiresAt->lte($today->copy()->addDays(7))) {
                return 'expiring_7';
            }

            if ($expiresAt->lte($today->copy()->addDays(30))) {
                return 'expiring_30';
            }

            return 'valid';
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expires_at' => 'date',
        ];
    }
}
