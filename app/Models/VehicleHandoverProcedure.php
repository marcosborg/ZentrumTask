<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleHandoverProcedure extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleHandoverProcedureFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'status',
        'draft_step',
        'vehicle_id',
        'driver_id',
        'operator_user_id',
        'closed_allocation_id',
        'created_allocation_id',
        'exchange_group_uuid',
        'exchange_related_procedure_id',
        'performed_at',
        'completed_at',
        'last_synced_at',
        'allocation_effective_start_date',
        'allocation_effective_end_date',
        'vehicle_snapshot',
        'driver_snapshot',
        'checklist_payload',
        'damage_items',
        'fault_items',
        'general_photo_paths',
        'guided_photo_items',
        'video_items',
        'battery_minimum_confirmed',
        'battery_minimum_percent',
        'deposit_paid_confirmed',
        'deposit_paid_amount',
        'notes',
        'operator_signature_data_url',
        'driver_signature_data_url',
        'html_snapshot',
        'pdf_path',
        'email_sent_at',
        'email_recipients',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    public function closedAllocation(): BelongsTo
    {
        return $this->belongsTo(VehicleAllocation::class, 'closed_allocation_id');
    }

    public function createdAllocation(): BelongsTo
    {
        return $this->belongsTo(VehicleAllocation::class, 'created_allocation_id');
    }

    public function exchangeRelatedProcedure(): BelongsTo
    {
        return $this->belongsTo(self::class, 'exchange_related_procedure_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'allocation_effective_start_date' => 'date',
            'allocation_effective_end_date' => 'date',
            'vehicle_snapshot' => 'array',
            'driver_snapshot' => 'array',
            'checklist_payload' => 'array',
            'damage_items' => 'array',
            'fault_items' => 'array',
            'general_photo_paths' => 'array',
            'guided_photo_items' => 'array',
            'video_items' => 'array',
            'battery_minimum_confirmed' => 'boolean',
            'battery_minimum_percent' => 'integer',
            'deposit_paid_confirmed' => 'boolean',
            'deposit_paid_amount' => 'decimal:2',
            'email_sent_at' => 'datetime',
            'email_recipients' => 'array',
        ];
    }
}
