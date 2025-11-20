<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecipientList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
    ];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class)->withTimestamps();
    }

    public function notificationRules()
    {
        return $this->hasMany(NotificationRule::class);
    }
}
