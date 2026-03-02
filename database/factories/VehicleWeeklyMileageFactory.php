<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleWeeklyMileage>
 */
class VehicleWeeklyMileageFactory extends Factory
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
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
            'weekly_km' => $this->faker->randomFloat(2, 500, 3500),
            'assignment_status' => 'ok',
            'raw_row' => ['origin' => 'factory'],
            'imported_at' => now(),
            'source_file' => 'factory.csv',
        ];
    }
}
