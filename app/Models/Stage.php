<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'board_id',
        'name',
        'slug',
        'color',
        'position',
        'is_initial',
        'is_final',
        'freeze_sla',
    ];

    public function board()
    {
        return $this->belongsTo(Board::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function notificationRules()
    {
        return $this->hasMany(NotificationRule::class);
    }
}
