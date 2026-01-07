<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleDocument>
 */
class VehicleDocumentFactory extends Factory
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
            'type' => $this->faker->randomElement(['DUA', 'INSURANCE', 'GREEN_CARD', 'INSPECTION', 'OTHER']),
            'title' => ucfirst($this->faker->words(2, true)),
            'document_number' => $this->faker->optional()->bothify('DOC-#####'),
            'issuer' => $this->faker->optional()->company(),
            'issue_date' => $this->faker->optional()->date(),
            'expires_at' => $this->faker->optional()->dateTimeBetween('-3 months', '+12 months')->format('Y-m-d'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
