<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\KanbanNotificationService;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'due_at' => 'datetime',
        'meta'   => 'array',
    ];

    protected $fillable = [
        'board_id',
        'stage_id',
        'assigned_to_id',
        'title',
        'description',
        'priority',
        'due_at',
        'position',
        'external_reference',
        'meta',
    ];

    public function board()
    {
        return $this->belongsTo(Board::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    protected static function booted()
    {
        static::updated(function (Task $task) {
            // Só reage se o stage tiver mudado
            if ($task->wasChanged('stage_id')) {
                app(KanbanNotificationService::class)
                    ->handleStageEntered($task);
            }
        });
    }
}
