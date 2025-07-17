<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(10),
            'content' => $this->faker->paragraphs(10, true),
            'summary' => $this->faker->paragraph(3),
            'source_url' => $this->faker->unique()->url(),
            'image_url' => 'https://picsum.photos/seed/' . $this->faker->unique()->word() . '/800/400',
            'author' => $this->faker->name(),
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'status' => $this->faker->randomElement([1, 2, 3]),
        ];
    }
}