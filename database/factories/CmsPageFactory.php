<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CmsPage>
 */
class CmsPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6),
            'highlight' => $this->faker->sentence(12),
            'body' => $this->faker->paragraphs(4, true),
            'is_active' => $this->faker->boolean(85),
            'meta_title' => $this->faker->sentence(8),
            'meta_description' => $this->faker->sentences(3, true),
        ];
    }
}
