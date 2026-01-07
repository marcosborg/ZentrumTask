<?php

namespace Database\Factories;

use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleDocumentAlert>
 */
class VehicleDocumentAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_document_id' => VehicleDocument::factory(),
            'level' => $this->faker->randomElement(['expiring_30', 'expiring_7', 'expired']),
            'triggered_on' => $this->faker->date(),
            'message' => $this->faker->sentence(),
            'is_resolved' => $this->faker->boolean(20),
            'resolved_at' => null,
        ];
    }
}
