<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    /** @use HasFactory<\Database\Factories\DriverFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'nif',
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
        'deposit_paid_at',
        'deposit_payment_method',
        'notes',
    ];

    public function billingProfiles(): HasMany
    {
        return $this->hasMany(DriverBillingProfile::class);
    }

    public function weekStatements(): HasMany
    {
        return $this->hasMany(DriverWeekStatement::class);
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
        ];
    }
}
