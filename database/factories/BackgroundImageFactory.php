<?php

namespace Database\Factories;

use App\Models\BackgroundImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackgroundImage>
 */
class BackgroundImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'path' => 'backgrounds/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'width' => 1080,
            'height' => 1920,
            'size_bytes' => fake()->numberBetween(50_000, 2_000_000),
        ];
    }
}
