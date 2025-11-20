<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'meta' => 'array',
    ];

    protected $fillable = [
        'name',
        'email',
        'type',
        'meta',
    ];

    public function recipientLists()
    {
        return $this->belongsToMany(RecipientList::class)->withTimestamps();
    }
}
