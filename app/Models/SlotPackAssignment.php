<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlotPackAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\SlotPackAssignmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function pack(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SlotPack::class, 'slot_pack_id');
    }

    public function participation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DriverParticipation::class, 'driver_participation_id');
    }
}
