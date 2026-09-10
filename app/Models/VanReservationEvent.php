<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VanReservationEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(VanReservation::class, 'van_reservation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
