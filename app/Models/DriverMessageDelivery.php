<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverMessageDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\DriverMessageDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'driver_message_campaign_id',
        'driver_id',
        'driver_name',
        'email_address',
        'phone_number',
        'email_status',
        'email_sent_at',
        'email_error',
        'whatsapp_status',
        'whatsapp_sent_at',
        'whatsapp_sent_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'email_sent_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DriverMessageCampaign::class, 'driver_message_campaign_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function whatsappSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'whatsapp_sent_by_user_id');
    }
}
