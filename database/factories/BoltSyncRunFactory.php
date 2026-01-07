<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BoltSyncRun>
 */
class BoltSyncRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_path' => $this->faker->filePath(),
            'status' => $this->faker->randomElement(['pending', 'running', 'completed']),
            'started_at' => $this->faker->optional()->dateTime(),
            'finished_at' => $this->faker->optional()->dateTime(),
            'row_count' => $this->faker->numberBetween(0, 500),
            'totals' => [
                'rows' => $this->faker->numberBetween(0, 500),
                'drivers' => $this->faker->numberBetween(0, 50),
                'amount' => $this->faker->randomFloat(2, 0, 5000),
            ],
            'meta' => [],
        ];
    }
}
