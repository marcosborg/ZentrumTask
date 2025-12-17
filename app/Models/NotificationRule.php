<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'stage_id',
        'message_template_id',
        'recipient_list_id',
        'trigger',
        'send_mode',
        'cooldown_hours',
        'also_send_to_assigned_user',
        'send_to_task_email',
        'is_active',
    ];

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function messageTemplate()
    {
        return $this->belongsTo(MessageTemplate::class);
    }

    public function recipientList()
    {
        return $this->belongsTo(RecipientList::class);
    }

    public function logs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'also_send_to_assigned_user' => 'boolean',
            'send_to_task_email' => 'boolean',
            'is_active' => 'boolean',
            'cooldown_hours' => 'integer',
        ];
    }
}
