<?php

namespace App\Models;

use App\Enums\TaxpayerType;
use App\Enums\VatRefundMode;
use App\Enums\VehicleRentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverBillingProfile extends Model
{
    /** @use HasFactory<\Database\Factories\DriverBillingProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'active',
        'valid_from',
        'valid_to',
        'taxpayer_type',
        'apply_withholding_tax',
        'withholding_tax_percent',
        'vat_percent',
        'vat_refund_mode',
        'percent_company',
        'percent_driver',
        'tips_to_driver',
        'vehicle_rent_type',
        'vehicle_rent_value',
        'additional_fixed_fee',
        'additional_percent_fee',
    ];

    protected $casts = [
        'active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'taxpayer_type' => TaxpayerType::class,
        'apply_withholding_tax' => 'boolean',
        'withholding_tax_percent' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'vat_refund_mode' => VatRefundMode::class,
        'percent_company' => 'decimal:2',
        'percent_driver' => 'decimal:2',
        'tips_to_driver' => 'boolean',
        'vehicle_rent_type' => VehicleRentType::class,
        'vehicle_rent_value' => 'decimal:2',
        'additional_fixed_fee' => 'decimal:2',
        'additional_percent_fee' => 'decimal:2',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function weekStatements(): HasMany
    {
        return $this->hasMany(DriverWeekStatement::class, 'billing_profile_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
