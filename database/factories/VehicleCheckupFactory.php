<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleCheckup>
 */
class VehicleCheckupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => \App\Models\Vehicle::factory()->state(['operation' => 'slot', 'owner_driver_id' => \App\Models\Driver::factory()->state(['registration_operation' => 'slot'])]),
            'scheduled_at' => now(), 'notes' => fake()->sentence(),
        ];
    }
}
