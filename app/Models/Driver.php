<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    /** @use HasFactory<\Database\Factories\DriverFactory> */
    use HasFactory;

    protected $fillable = [
        'registration_operation',
        'candidate_application_id',
        'bolt_driver_uuid',
        'uber_driver_uuid',
        'bolt_driver_code',
        'uber_driver_code',
        'company_id',
        'name',
        'email',
        'phone',
        'nif',
        'sns_number',
        'niss_number',
        'iban',
        'license_number',
        'date_of_birth',
        'nationality',
        'marital_status',
        'address',
        'identity_document_type',
        'identity_document_number',
        'identity_document_expires_at',
        'emergency_contact_name',
        'emergency_contact_phone',
        'license_issued_at',
        'license_expires_at',
        'license_category',
        'tvde_certificate_number',
        'tvde_certificate_expires_at',
        'tvde_platforms',
        'bank_account_holder',
        'deposit_amount',
        'deposit_initial_amount',
        'deposit_paid_at',
        'deposit_payment_method',
        'contract_file',
        'other_documents',
        'notes',
    ];

    public function billingProfiles(): HasMany
    {
        return $this->hasMany(DriverBillingProfile::class)->where('operation', 'rental');
    }

    protected static function booted(): void
    {
        static::updated(function (self $driver): void {
            $fields = ['deposit_amount', 'deposit_initial_amount', 'deposit_paid_at', 'deposit_payment_method'];
            if ($driver->wasChanged($fields)) {
                $date = now(config('slots.timezone'))->toDateString();
                $participation = $driver->participations()->where('operation', 'rental')->where('status', '!=', 'preparing')->whereDate('starts_at', '<=', $date)->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>', $date))->first();
                $participation?->update($driver->only($fields));
            }
        });
        static::created(function (self $driver): void {
            if (($driver->registration_operation ?? 'rental') === 'rental') {
                $driver->participations()->create(['operation' => 'rental', 'status' => 'active', 'is_legacy' => true, 'starts_at' => $driver->created_at->toDateString(), 'contract_file' => $driver->contract_file, 'deposit_amount' => $driver->deposit_amount ?? 0, 'deposit_initial_amount' => $driver->deposit_initial_amount ?? 0, 'deposit_paid_at' => $driver->deposit_paid_at, 'deposit_payment_method' => $driver->deposit_payment_method]);
            }
        });
    }

    public function participations(): HasMany
    {
        return $this->hasMany(DriverParticipation::class);
    }

    public function scopeForOperation(\Illuminate\Database\Eloquent\Builder $query, \App\Enums\TvdeOperation $operation): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('participations', fn ($query) => $query->where('operation', $operation->value));
    }

    public function scopeCurrentlyInOperation(\Illuminate\Database\Eloquent\Builder $query, \App\Enums\TvdeOperation $operation): \Illuminate\Database\Eloquent\Builder
    {
        $date = now(config('slots.timezone'))->toDateString();

        return $query->whereHas('participations', fn ($query) => $query
            ->where('operation', $operation->value)->where('status', '!=', 'preparing')
            ->whereDate('starts_at', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>', $date)));
    }

    public function billingProfile(): HasOne
    {
        return $this->hasOne(DriverBillingProfile::class)->where('operation', 'rental');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VehicleAllocation::class)->where('operation', 'rental');
    }

    public function currentAllocation(): HasOne
    {
        return $this->hasOne(VehicleAllocation::class)
            ->where('operation', 'rental')
            ->whereIn('status', ['active', 'closed'])
            ->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->latest('starts_at');
    }

    public function weekStatements(): HasMany
    {
        return $this->hasMany(DriverWeekStatement::class)->where('operation', 'rental');
    }

    public function balance(): HasOne
    {
        return $this->hasOne(DriverBalance::class)->where('operation', 'rental')->latest('id');
    }

    public function balanceMovements(): HasMany
    {
        return $this->hasMany(DriverBalanceMovement::class)->where('operation', 'rental');
    }

    public function depositDebits(): HasMany
    {
        return $this->hasMany(DriverDepositDebit::class)->where('operation', 'rental');
    }

    public function messageDeliveries(): HasMany
    {
        return $this->hasMany(DriverMessageDelivery::class);
    }

    public function candidateApplication(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function hasActiveBillingProfile(): Attribute
    {
        return Attribute::get(function (): bool {
            $value = $this->attributes['has_active_billing_profile'] ?? null;

            if ($value !== null) {
                return (bool) $value;
            }

            return $this->billingProfiles()->active()->exists();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_active_billing_profile' => 'boolean',
            'date_of_birth' => 'date',
            'identity_document_expires_at' => 'date',
            'license_issued_at' => 'date',
            'license_expires_at' => 'date',
            'tvde_certificate_expires_at' => 'date',
            'deposit_paid_at' => 'date',
            'tvde_platforms' => 'array',
            'deposit_amount' => 'decimal:2',
            'deposit_initial_amount' => 'decimal:2',
            'other_documents' => 'array',
        ];
    }
}
