<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->randomNumber(5),
            'excerpt' => $this->faker->paragraph(),
            'body' => $this->faker->paragraphs(4, true),
            'is_published' => true,
            'published_at' => now()->subDays($this->faker->numberBetween(0, 30)),
            'meta_title' => null,
            'meta_description' => null,
        ];
    }
}
