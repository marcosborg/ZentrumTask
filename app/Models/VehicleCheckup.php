<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleCheckup extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleCheckupFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (self $checkup): void {
            if ($checkup->exists && $checkup->getOriginal('completed_at') && $checkup->isDirty(['vehicle_id', 'completed_at'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['completed_at' => 'Preserve o check-up concluído e o histórico desta viatura.']);
            }
            if (Vehicle::query()->find($checkup->vehicle_id)?->operation !== 'slot') {
                throw \Illuminate\Validation\ValidationException::withMessages(['vehicle_id' => 'Selecione uma viatura SLOT.']);
            }
            if ($checkup->completed_at && $checkup->isDirty('completed_at')) {
                $previous = self::query()->where('vehicle_id', $checkup->vehicle_id)->when($checkup->id, fn ($q) => $q->whereKeyNot($checkup->id))->whereNotNull('completed_at')->latest('completed_at')->first();
                if ($previous && $checkup->completed_at->lt($previous->nextDueAt())) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['completed_at' => 'O check-up gratuito é anual; preserve o histórico da viatura.']);
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'completed_at' => 'datetime', 'attachments' => 'array'];
    }

    public function nextDueAt(): ?\Illuminate\Support\Carbon
    {
        return $this->completed_at?->copy()->setTimezone(config('slots.timezone'))->addYearNoOverflow();
    }

    public function vehicle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function responsible(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
