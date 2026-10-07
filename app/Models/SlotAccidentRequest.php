<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlotAccidentRequest extends Model
{
    /** @use HasFactory<\Database\Factories\SlotAccidentRequestFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['requested_at' => 'date', 'resolved_at' => 'date', 'amount_requested' => 'decimal:2', 'amount_awarded' => 'decimal:2'];
    }

    public function accident(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SlotAccident::class, 'slot_accident_id');
    }
}
