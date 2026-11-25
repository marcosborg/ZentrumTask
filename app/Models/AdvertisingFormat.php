<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvertisingFormat extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'image', 'active'];

    public const FLEET = ['1 a 5', '6 a 10', '11 a 25', '26 a 50', 'Mais de 50'];

    public const DURATIONS = ['1 mês', '3 meses', '6 meses', '12 meses', 'Personalizada'];
}
