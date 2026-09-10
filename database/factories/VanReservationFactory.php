<?php

namespace Database\Factories;

use App\Models\RentalVan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VanReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rental_van_id' => RentalVan::factory(), 'reference' => 'CAR-'.Str::ulid(), 'submission_key' => Str::uuid(),
            'status' => 'pending', 'mode' => 'self_drive', 'starts_at' => now()->addDays(2)->setTime(9, 0), 'ends_at' => now()->addDays(2)->setTime(11, 0),
            'buffer_minutes' => 30, 'hourly_rate' => 1500, 'billable_hours' => 2, 'estimated_total' => 3000,
            'deposit' => 25000, 'terms' => ['minimum_hours' => 1],
            'name' => fake()->name(), 'email' => fake()->safeEmail(), 'phone' => '912345678', 'purpose' => 'goods',
        ];
    }
}
