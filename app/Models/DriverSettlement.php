<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'company_share',
        'driver_share',
        'amount_payable',
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
            'company_share' => 'decimal:2',
            'driver_share' => 'decimal:2',
            'amount_payable' => 'decimal:2',
            'rules_snapshot' => 'array',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
