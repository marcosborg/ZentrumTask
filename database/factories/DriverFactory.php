<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'nif' => $this->faker->numerify('############'),
            'iban' => 'PT50'.$this->faker->numerify(str_repeat('#', 21)),
            'license_number' => $this->faker->bothify('LX########'),
            'notes' => $this->faker->sentence(),
        ];
    }
}
