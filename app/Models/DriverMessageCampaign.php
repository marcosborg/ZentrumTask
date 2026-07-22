<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverMessageCampaign extends Model
{
    /** @use HasFactory<\Database\Factories\DriverMessageCampaignFactory> */
    use HasFactory;

    protected $fillable = [
        'created_by_user_id',
        'subject',
        'body',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(DriverMessageDelivery::class);
    }
}
