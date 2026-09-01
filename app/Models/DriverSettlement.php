<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverSettlement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'period_start',
        'period_end',
        'net_total',
        'tips_total',
        'expenses_total',
        'tesla_charging_total',
        'carry_over_balance',
        'company_share',
        'driver_share',
        'amount_payable',
        'amount_due',
        'amount_transferred',
        'is_paid',
        'email_sent_count',
        'last_emailed_at',
        'last_emailed_to',
        'paid_at',
        'green_receipt_path',
        'green_receipt_uploaded_at',
        'green_receipt_uploaded_by_user_id',
        'rules_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'net_total' => 'decimal:2',
            'tips_total' => 'decimal:2',
            'expenses_total' => 'decimal:2',
            'tesla_charging_total' => 'decimal:2',
            'carry_over_balance' => 'decimal:2',
            'company_share' => 'decimal:2',
            'driver_share' => 'decimal:2',
            'amount_payable' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'amount_transferred' => 'decimal:2',
            'is_paid' => 'boolean',
            'email_sent_count' => 'integer',
            'last_emailed_at' => 'datetime',
            'paid_at' => 'datetime',
            'green_receipt_uploaded_at' => 'datetime',
            'green_receipt_uploaded_by_user_id' => 'integer',
            'rules_snapshot' => 'array',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(SettlementEmailLog::class);
    }

    public function greenReceiptUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'green_receipt_uploaded_by_user_id');
    }
}
