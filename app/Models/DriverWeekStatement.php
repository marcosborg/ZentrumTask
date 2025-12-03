<?php

namespace App\Models;

use App\Enums\StatementStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverWeekStatement extends Model
{
    /** @use HasFactory<\Database\Factories\DriverWeekStatementFactory> */
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'billing_profile_id',
        'tvde_week_id',
        'week_start_date',
        'week_end_date',
        'gross_total',
        'net_total',
        'tips_total',
        'company_share',
        'driver_share',
        'vat_amount',
        'withholding_amount',
        'expenses_total',
        'rent_amount',
        'additional_fees_total',
        'amount_payable_to_driver',
        'status',
        'calculated_at',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'gross_total' => 'decimal:2',
        'net_total' => 'decimal:2',
        'tips_total' => 'decimal:2',
        'company_share' => 'decimal:2',
        'driver_share' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'expenses_total' => 'decimal:2',
        'rent_amount' => 'decimal:2',
        'additional_fees_total' => 'decimal:2',
        'amount_payable_to_driver' => 'decimal:2',
        'status' => StatementStatus::class,
        'calculated_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function billingProfile(): BelongsTo
    {
        return $this->belongsTo(DriverBillingProfile::class, 'billing_profile_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DriverWeekStatementItem::class)->orderBy('created_at');
    }

    public function isDraft(): bool
    {
        return $this->status === StatementStatus::Draft;
    }

    public function isConfirmed(): bool
    {
        return $this->status === StatementStatus::Confirmed;
    }

    public function isPaid(): bool
    {
        return $this->status === StatementStatus::Paid;
    }

    protected function weekLabel(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->week_start_date && ! $this->week_end_date) {
                return null;
            }

            if (! $this->week_start_date || ! $this->week_end_date) {
                return $this->week_start_date?->format('d/m/Y') ?? $this->week_end_date?->format('d/m/Y');
            }

            return $this->week_start_date->format('d/m/Y').' - '.$this->week_end_date->format('d/m/Y');
        });
    }
}
