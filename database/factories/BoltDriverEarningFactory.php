<?php

namespace Database\Factories;

use App\Models\BoltSyncRun;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BoltDriverEarning>
 */
class BoltDriverEarningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bolt_sync_run_id' => BoltSyncRun::factory(),
            'driver_id' => Driver::factory(),
            'bolt_driver_identifier' => $this->faker->numerify('BOLT###'),
            'bolt_driver_name' => $this->faker->name(),
            'bolt_driver_email' => $this->faker->safeEmail(),
            'week_start' => $this->faker->date(),
            'week_end' => $this->faker->date(),
            'total_amount' => $this->faker->randomFloat(2, 10, 1200),
            'currency' => 'EUR',
            'raw_payload' => [
                'source' => 'factory',
            ],
        ];
    }
}
