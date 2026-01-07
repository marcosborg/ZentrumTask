<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleAllocation>
 */
class VehicleAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => Driver::factory(),
            'starts_at' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'ends_at' => $this->faker->optional()->dateTimeBetween('now', '+2 months'),
            'start_odometer' => $this->faker->optional()->numberBetween(0, 200000),
            'end_odometer' => $this->faker->optional()->numberBetween(0, 250000),
            'status' => $this->faker->randomElement(['active', 'ended', 'cancelled']),
            'handover_location' => $this->faker->optional()->city(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
