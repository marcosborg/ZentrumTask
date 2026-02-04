<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverAdjustment extends Model
{
    /** @use HasFactory<\Database\Factories\DriverAdjustmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'starts_at',
        'recurrence_weeks',
        'category',
        'description',
        'amount',
        'external_ref',
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
            'starts_at' => 'date',
            'amount' => 'decimal:2',
            'recurrence_weeks' => 'integer',
            'raw_row' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
