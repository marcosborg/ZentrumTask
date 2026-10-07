<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlotAccidentRequest>
 */
class SlotAccidentRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slot_accident_id' => \App\Models\SlotAccident::factory(), 'type' => 'immobilization', 'status' => 'preparing',
        ];
    }
}
