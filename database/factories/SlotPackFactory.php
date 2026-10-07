<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlotPack>
 */
class SlotPackFactory extends Factory
{
    /**
     * Define the model's default state.

     *

     * @return array<string, mixed>
     */
    public function definition(): array
    {

        return [

            'code' => fake()->unique()->word(), 'name' => 'Base', 'weekly_price' => 30,

            'valid_from' => '2020-01-01', 'benefits' => 'Check-up anual e oficina prioritária',

        ];

    }
}
