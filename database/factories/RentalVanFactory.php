<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RentalVanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Carrinha demonstração', 'license_plate' => fake()->unique()->bothify('DE-##-??'), 'status' => 'draft',
            'description' => 'Carrinha de demonstração para mercadorias e mudanças.',
            'photos' => ['rental-vans/test.jpg'], 'volume_m3' => 12, 'payload_kg' => 1200,
            'cargo_dimensions' => '320 × 175 × 190 cm', 'seats' => 3, 'fuel' => 'Gasóleo', 'transmission' => 'Manual',
            'equipment' => ['Cintas de fixação'], 'self_drive' => true, 'with_driver' => true,
            'self_drive_rate' => 1500, 'with_driver_rate' => 3000, 'deposit' => 25000,
            'minimum_hours' => 1, 'buffer_minutes' => 30, 'lead_hours' => 2,
            'opens_at' => '08:00:00', 'closes_at' => '20:00:00', 'pickup_location' => 'Local de demonstração',
            'mileage_terms' => 'Condições de demonstração — definir quilometragem.',
            'fuel_terms' => 'Devolver com o mesmo nível de combustível.',
            'cancellation_terms' => 'Condições de demonstração — definir cancelamento.',
            'rental_terms' => 'Exemplo de demonstração; não constitui oferta comercial.',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published']);
    }
}
