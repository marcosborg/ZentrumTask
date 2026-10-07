<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformDriverBalance extends Model
{
    use \App\Models\Concerns\BelongsToParticipation;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'allocation_error',
        'driver_participation_id',
        'operation',
        'platform',
        'driver_code',
        'period_start',
        'period_end',
        'net_amount',
        'tips_amount',
        'net_source_column',
        'tips_source_column',
        'raw_row',
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
            'raw_row' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
