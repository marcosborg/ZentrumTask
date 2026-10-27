<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class VehicleRevision extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleRevisionFactory> */
    use HasFactory;

    protected $fillable = ['vehicle_id', 'scheduled_at', 'completed_at', 'odometer', 'next_odometer', 'interval_years', 'notes'];

    protected static function booted(): void
    {
        static::saving(function (self $revision): void {
            Validator::make($revision->getAttributes(), [
                'scheduled_at' => ['nullable', 'date'],
                'completed_at' => ['nullable', 'date', 'before_or_equal:today'],
                'odometer' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
                'next_odometer' => ['nullable', 'integer', 'min:1', 'max:4294967295', ...($revision->odometer !== null ? ['gt:odometer'] : [])],
                'interval_years' => ['nullable', 'integer', 'min:1', 'max:100'],
                'notes' => ['nullable', 'string'],
            ], [
                'before_or_equal' => 'A data de realização não pode ser futura.',
                'gt' => 'A próxima revisão deve ter mais quilómetros do que a revisão realizada.',
                'integer' => 'Indique um número inteiro.',
                'min' => 'O valor deve ser pelo menos :min.',
                'max' => 'O valor não pode exceder :max.',
            ])->validate();
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function isRecorded(): bool
    {
        return $this->completed_at !== null || $this->odometer !== null
            || ($this->scheduled_at === null && $this->next_odometer !== null);
    }

    protected function casts(): array
    {
        return ['scheduled_at' => 'date', 'completed_at' => 'date', 'odometer' => 'integer', 'next_odometer' => 'integer', 'interval_years' => 'integer'];
    }
}
