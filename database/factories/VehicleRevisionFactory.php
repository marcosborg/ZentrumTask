<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleRevision>
 */
class VehicleRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => \App\Models\Vehicle::factory(),
            'completed_at' => today()->subMonths(6),
            'odometer' => 80000,
            'next_odometer' => 100000,
            'interval_years' => 1,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['completed_at' => null, 'scheduled_at' => today()->addWeek(), 'odometer' => null, 'next_odometer' => null, 'interval_years' => 1]);
    }
}
