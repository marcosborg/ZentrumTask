<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand',
        'model',
        'version',
        'weekly_rental_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekly_rental_price' => 'decimal:2',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        return trim((string) collect([$this->brand, $this->model, $this->version])->filter()->implode(' '));
    }
}
