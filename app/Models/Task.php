<?php

namespace App\Models;

use App\Services\KanbanNotificationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'due_at' => 'datetime',
        'first_interaction_at' => 'datetime',
        'stage_entered_at' => 'datetime',
        'meta' => 'array',
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
        'first_interaction_at',
        'stage_entered_at',
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
        static::creating(function (Task $task) {
            $task->stage_entered_at ??= now();
        });

        static::updating(function (Task $task) {
            if ($task->isDirty('stage_id')) {
                $task->stage_entered_at = now();

                if ($task->first_interaction_at === null) {
                    $task->first_interaction_at = now();
                }
            }
        });

        static::updated(function (Task $task) {
            if ($task->wasChanged('stage_id')) {
                app(KanbanNotificationService::class)
                    ->handleStageEntered($task);
            }
        });
    }

    public function markFirstInteraction(?CarbonInterface $timestamp = null): void
    {
        if ($this->first_interaction_at !== null) {
            return;
        }

        $this->forceFill([
            'first_interaction_at' => $timestamp ?? now(),
        ])->saveQuietly();
    }
}
