<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverParticipation extends Model
{
    /** @use HasFactory<\Database\Factories\DriverParticipationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (self $participation): void {
            if ($participation->ends_at && $participation->ends_at->lte($participation->starts_at)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['ends_at' => 'O fim deve ser posterior ao início.']);
            }
            if ($participation->exists && $participation->isDirty(['operation', 'driver_id', 'starts_at', 'ends_at', 'vehicle_id']) && $participation->settlements()->exists()) {
                if ($participation->isDirty(['operation', 'driver_id', 'starts_at', 'vehicle_id']) || ($participation->ends_at && $participation->settlements()->whereDate('period_end', '>=', $participation->ends_at)->exists())) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['starts_at' => 'Preserve a participação e os settlements processados.']);
                }
            }
            if ($participation->status !== 'preparing') {
                $overlap = self::query()->where('driver_id', $participation->driver_id)->where('status', '!=', 'preparing')->when($participation->id, fn ($q) => $q->whereKeyNot($participation->id))->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>', $participation->starts_at));
                if ($participation->ends_at) {
                    $overlap->whereDate('starts_at', '<', $participation->ends_at);
                }
                if ($overlap->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['starts_at' => 'O motorista já tem uma participação nesse período.']);
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['operation' => \App\Enums\TvdeOperation::class, 'starts_at' => 'date', 'ends_at' => 'date', 'documents_approved_at' => 'datetime', 'deposit_amount' => 'decimal:2', 'deposit_initial_amount' => 'decimal:2', 'deposit_paid_at' => 'date'];
    }

    public function scopeForOperation(\Illuminate\Database\Eloquent\Builder $query, \App\Enums\TvdeOperation $operation): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('operation', $operation->value);
    }

    public function activeDuring(string $start, string $end): bool
    {
        if ($this->status === 'preparing' || $this->starts_at->toDateString() > $end || ($this->ends_at && $this->ends_at->toDateString() <= $start)) {
            return false;
        }
        for ($day = \Illuminate\Support\Carbon::parse($start); $day->toDateString() <= $end; $day->addDay()) {
            $date = $day->toDateString();
            if ($date < $this->starts_at->toDateString() || ($this->ends_at && $date >= $this->ends_at->toDateString())) {
                continue;
            }
            if (! $this->suspensions()->whereDate('starts_at', '<=', $date)->whereDate('ends_at', '>=', $date)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function currentStatus(): string
    {
        $date = now(config('slots.timezone'))->toDateString();
        if ($this->status === 'preparing') {
            return 'preparing';
        }
        if ($this->starts_at->toDateString() > $date) {
            return 'scheduled';
        }
        if ($this->ends_at && $this->ends_at->toDateString() <= $date) {
            return 'closed';
        }

        return $this->suspensions()->whereDate('starts_at', '<=', $date)->whereDate('ends_at', '>=', $date)->exists() ? 'suspended' : 'active';
    }

    public function driver(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function suspensions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ParticipationSuspension::class);
    }

    public function packs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SlotPackAssignment::class);
    }

    public function billingProfiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DriverBillingProfile::class);
    }

    public function balance(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DriverBalance::class);
    }

    public function settlements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DriverSettlement::class);
    }
}
