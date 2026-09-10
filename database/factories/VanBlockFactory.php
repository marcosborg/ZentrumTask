<?php

namespace Database\Factories;

use App\Models\RentalVan;
use Illuminate\Database\Eloquent\Factories\Factory;

class VanBlockFactory extends Factory
{
    public function definition(): array
    {
        return ['rental_van_id' => RentalVan::factory(), 'starts_at' => now()->addDays(2)->setTime(9, 0), 'ends_at' => now()->addDays(2)->setTime(11, 0), 'reason' => 'Manutenção'];
    }
}
