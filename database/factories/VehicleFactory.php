<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'license_plate' => strtoupper($this->faker->bothify('??-##-??')),
            'vin' => $this->faker->optional()->passthrough(
                $this->faker->unique()->regexify('[A-HJ-NPR-Z0-9]{17}')
            ),
            'make' => ucfirst($this->faker->word()),
            'model' => ucfirst($this->faker->word()),
            'trim' => $this->faker->optional()->word(),
            'year' => $this->faker->numberBetween(2005, (int) date('Y')),
            'fuel_type' => $this->faker->randomElement(['diesel', 'gasoline', 'hybrid', 'electric']),
            'transmission' => $this->faker->randomElement(['manual', 'auto']),
            'color' => $this->faker->safeColorName(),
            'seats' => $this->faker->numberBetween(2, 7),
            'engine_cc' => $this->faker->numberBetween(900, 3500),
            'power_kw' => $this->faker->numberBetween(50, 200),
            'current_odometer' => $this->faker->numberBetween(0, 250000),
            'status' => $this->faker->randomElement(['available', 'allocated', 'maintenance']),
            'acquisition_date' => $this->faker->optional()->date(),
            'acquisition_cost' => $this->faker->optional()->randomFloat(2, 5000, 60000),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
