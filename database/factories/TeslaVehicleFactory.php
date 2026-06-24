<?php

namespace Database\Factories;

use App\Models\TeslaAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeslaVehicle>
 */
class TeslaVehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tesla_account_id' => TeslaAccount::factory(),
            'tesla_vehicle_id' => (string) fake()->unique()->randomNumber(8),
            'vin' => fake()->unique()->regexify('[A-HJ-NPR-Z0-9]{17}'),
            'display_name' => fake()->words(2, true),
            'state' => fake()->randomElement(['online', 'asleep', 'offline']),
            'model' => fake()->randomElement(['Model 3', 'Model Y']),
            'odometer' => fake()->randomFloat(2, 1000, 200000),
            'battery_level' => fake()->numberBetween(10, 100),
            'raw_payload' => [],
            'last_seen_at' => null,
        ];
    }
}
