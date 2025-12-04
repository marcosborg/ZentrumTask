<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebsiteMenuItem>
 */
class WebsiteMenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => $this->faker->words(2, true),
            'url' => $this->faker->boolean(80) ? $this->faker->url() : null,
            'position' => $this->faker->numberBetween(1, 20),
            'parent_id' => null,
        ];
    }
}
