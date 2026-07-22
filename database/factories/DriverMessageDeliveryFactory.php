<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\DriverMessageCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DriverMessageDelivery>
 */
class DriverMessageDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_message_campaign_id' => DriverMessageCampaign::factory(),
            'driver_id' => Driver::factory(),
            'driver_name' => fake()->name(),
            'email_address' => fake()->safeEmail(),
            'phone_number' => '3519'.fake()->numerify('########'),
            'email_status' => 'pending',
            'whatsapp_status' => 'pending',
        ];
    }
}
