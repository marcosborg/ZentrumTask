<?php

namespace App\Models\Concerns;

trait BelongsToParticipation
{
    public function participation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\DriverParticipation::class, 'driver_participation_id');
    }

    public function scopeForOperation(\Illuminate\Database\Eloquent\Builder $query, \App\Enums\TvdeOperation $operation): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where($this->qualifyColumn('operation'), $operation->value);
    }

    protected static function bootBelongsToParticipation(): void
    {
        static::saving(function ($record): void {
            if ($record->driver_participation_id) {
                $participation = \App\Models\DriverParticipation::query()->findOrFail($record->driver_participation_id);
                if ((int) $record->driver_id !== (int) $participation->driver_id) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['driver_id' => 'Motorista incompatível com a participação.']);
                }
                $record->operation = $participation->operation->value;
                foreach (['driver_balance_id' => \App\Models\DriverBalance::class, 'driver_settlement_id' => \App\Models\DriverSettlement::class] as $key => $model) {
                    if ($record->{$key} && (int) $model::query()->findOrFail($record->{$key})->driver_participation_id !== (int) $participation->id) {
                        throw \Illuminate\Validation\ValidationException::withMessages([$key => 'O movimento deve permanecer na participação de origem.']);
                    }
                }
            } elseif ($record->driver_id && ($record->operation ?? 'rental') === 'rental') {
                $date = $record->period_start ?? $record->week_start_date ?? $record->valid_from ?? $record->starts_at ?? $record->occurred_at ?? now();
                $participation = app(\App\Services\ParticipationService::class)->resolve((int) $record->driver_id, $date);
                if ($participation?->operation !== \App\Enums\TvdeOperation::Rental) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['driver_id' => 'Selecione uma participação de origem válida.']);
                }
                $record->driver_participation_id = $participation->id;
                $record->operation = 'rental';
            } elseif ($record->driver_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['driver_participation_id' => 'A participação SLOT é obrigatória.']);
            }
        });
    }
}
