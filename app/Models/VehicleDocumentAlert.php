<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDocumentAlert extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleDocumentAlertFactory> */
    use HasFactory;

    protected $fillable = [
        'vehicle_document_id',
        'level',
        'triggered_on',
        'message',
        'is_resolved',
        'resolved_at',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(VehicleDocument::class, 'vehicle_document_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'triggered_on' => 'date',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }
}
