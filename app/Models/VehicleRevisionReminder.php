<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleRevisionReminder extends Model
{
    protected $fillable = ['vehicle_revision_id', 'user_id', 'cycle', 'sent_on'];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(VehicleRevision::class, 'vehicle_revision_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['sent_on' => 'date'];
    }
}
