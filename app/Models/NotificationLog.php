<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationLog extends Model
{
    use HasFactory;

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected $fillable = [
        'notification_rule_id',
        'task_id',
        'to_email',
        'subject',
        'status',
        'error_message',
        'sent_at',
    ];

    public function rule()
    {
        return $this->belongsTo(NotificationRule::class, 'notification_rule_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
