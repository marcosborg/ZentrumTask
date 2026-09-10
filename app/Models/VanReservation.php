<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VanReservation extends Model
{
    use HasFactory;

    public const STATUSES = ['pending' => 'Pendente', 'confirmed' => 'Confirmada', 'rejected' => 'Rejeitada', 'cancelled' => 'Cancelada', 'in_progress' => 'Em curso', 'completed' => 'Concluída'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'terms' => 'array', 'loading_help' => 'boolean', 'driver_verified' => 'boolean'];
    }

    public function van(): BelongsTo
    {
        return $this->belongsTo(RentalVan::class, 'rental_van_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(VanReservationEvent::class)->latest('id');
    }
}
