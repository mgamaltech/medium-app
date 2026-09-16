<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
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
            'title' => $this->faker->sentence,
            'body' => $this->faker->paragraph,
            'status' => ArticleStatus::PUBLISHED,
            'cover_image' => $this->faker->imageUrl(),
            'user_id' => User::factory(),
        ];
    }
}
