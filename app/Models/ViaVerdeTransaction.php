<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViaVerdeTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\ViaVerdeTransactionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'occurred_at',
        'vehicle_plate',
        'location',
        'type',
        'amount',
        'external_ref',
        'vehicle_id',
        'driver_id',
        'assignment_status',
        'raw_row',
        'imported_at',
        'source_file',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'amount' => 'decimal:2',
            'raw_row' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
