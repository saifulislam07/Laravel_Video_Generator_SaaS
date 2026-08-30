<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'thumbnail_path' => 'characters/thumbnails/'.fake()->uuid().'.png',
            'is_public' => false,
        ];
    }

    /** System-default character: no owner, public. */
    public function system(): static
    {
        return $this->state([
            'user_id' => null,
            'is_public' => true,
        ]);
    }
}
