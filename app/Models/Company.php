<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'nif',
        'address',
        'city',
        'postal_code',
        'country',
        'iban',
    ];

    /**
     * @return HasMany<Driver>
     */
    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }
}
