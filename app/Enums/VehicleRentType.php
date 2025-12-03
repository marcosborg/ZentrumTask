<?php

namespace App\Enums;

enum VehicleRentType: string
{
    case None = 'none';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case PerKm = 'per_km';
}
