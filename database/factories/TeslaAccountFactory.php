<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeslaAccount>
 */
class TeslaAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tesla_user_id' => (string) fake()->unique()->randomNumber(8),
            'email' => fake()->safeEmail(),
            'owner_email' => fake()->safeEmail(),
            'access_token' => encrypt(fake()->sha256()),
            'refresh_token' => encrypt(fake()->sha256()),
            'scopes' => explode(' ', 'openid offline_access vehicle_device_data vehicle_location'),
            'expires_at' => now()->addHour(),
            'last_synced_at' => null,
        ];
    }
}
