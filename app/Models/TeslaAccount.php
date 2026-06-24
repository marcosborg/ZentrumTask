<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeslaAccount extends Model
{
    /** @use HasFactory<\Database\Factories\TeslaAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tesla_user_id',
        'email',
        'owner_email',
        'access_token',
        'refresh_token',
        'scopes',
        'expires_at',
        'last_synced_at',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(TeslaVehicle::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
