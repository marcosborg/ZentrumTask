<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlotAccident>
 */
class SlotAccidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_participation_id' => \App\Models\DriverParticipation::factory()->state(['vehicle_id' => \App\Models\Vehicle::factory()->state(['operation' => 'slot', 'owner_driver_id' => \App\Models\Driver::factory()->state(['registration_operation' => 'slot'])])]),
            'vehicle_id' => fn (array $attributes) => \App\Models\DriverParticipation::findOrFail($attributes['driver_participation_id'])->vehicle_id,
            'occurred_at' => now(), 'status' => 'open',
        ];
    }
}
