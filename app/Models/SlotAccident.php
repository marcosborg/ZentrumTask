<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlotAccident extends Model
{
    /** @use HasFactory<\Database\Factories\SlotAccidentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'immobilized_at' => 'datetime', 'repair_started_at' => 'datetime', 'repair_completed_at' => 'datetime', 'documents' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $accident): void {
            $participation = DriverParticipation::query()->find($accident->driver_participation_id);
            $date = $accident->occurred_at?->copy()->setTimezone(config('slots.timezone'))->toDateString();
            if (! $date || ! $participation || $date < $participation->starts_at->toDateString() || ($participation->ends_at && $date >= $participation->ends_at->toDateString()) || ($accident->exists && $accident->isDirty(['vehicle_id', 'driver_participation_id']))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['occurred_at' => 'O sinistro deve permanecer na participação e no período de origem.']);
            }
            if ($participation?->operation !== \App\Enums\TvdeOperation::Slot || (int) $participation->vehicle_id !== (int) $accident->vehicle_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['vehicle_id' => 'Sinistro incompatível com a participação SLOT.']);
            }
        });
    }

    public function participation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DriverParticipation::class, 'driver_participation_id');
    }

    public function vehicle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function requests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SlotAccidentRequest::class);
    }
}
