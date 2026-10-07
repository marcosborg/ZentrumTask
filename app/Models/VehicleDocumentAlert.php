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
        'document_expires_at',
        'level',
        'triggered_on',
        'message',
        'is_resolved',
        'resolved_at',
    ];

    protected static function booted(): void
    {
        static::updated(function (self $alert): void {
            if (! $alert->wasChanged('is_resolved') || $alert->document_expires_at === null) {
                return;
            }

            self::query()
                ->where('vehicle_document_id', $alert->vehicle_document_id)
                ->whereDate('document_expires_at', $alert->document_expires_at)
                ->where('is_resolved', ! $alert->is_resolved)
                ->update([
                    'is_resolved' => $alert->is_resolved,
                    'resolved_at' => $alert->is_resolved ? ($alert->resolved_at ?? now()) : null,
                ]);
        });
    }

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
            'document_expires_at' => 'date',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }
}
