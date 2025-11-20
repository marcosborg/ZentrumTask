<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Board extends Model
{

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'position',
    ];

    public function stages()
    {
        return $this->hasMany(Stage::class)->orderBy('position');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
