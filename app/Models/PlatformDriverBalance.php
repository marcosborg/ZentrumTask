<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformDriverBalance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'platform',
        'driver_code',
        'period_start',
        'period_end',
        'net_amount',
        'tips_amount',
        'source_file',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'net_amount' => 'decimal:2',
            'tips_amount' => 'decimal:2',
            'imported_at' => 'datetime',
        ];
    }
}
