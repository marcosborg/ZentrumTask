<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DriverParticipation>
 */
class DriverParticipationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => \App\Models\Driver::factory()->state(['registration_operation' => 'slot']),
            'operation' => 'slot', 'status' => 'preparing', 'starts_at' => now()->startOfWeek()->toDateString(),
        ];
    }
}
