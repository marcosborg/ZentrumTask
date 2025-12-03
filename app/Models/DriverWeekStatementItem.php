<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverWeekStatementItem extends Model
{
    /** @use HasFactory<\Database\Factories\DriverWeekStatementItemFactory> */
    use HasFactory;

    protected $fillable = [
        'driver_week_statement_id',
        'type',
        'description',
        'amount',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(DriverWeekStatement::class, 'driver_week_statement_id');
    }
}
