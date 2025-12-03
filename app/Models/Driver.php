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

    protected $casts = [
        'has_active_billing_profile' => 'boolean',
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'nif',
        'iban',
        'license_number',
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
}
