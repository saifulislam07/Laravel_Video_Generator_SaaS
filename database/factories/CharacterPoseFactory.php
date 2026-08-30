<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterPose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterPose>
 */
class CharacterPoseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'pose_name' => fake()->randomElement(['idle', 'smiling', 'surprised']),
            'image_path' => 'characters/poses/'.fake()->uuid().'.png',
        ];
    }
}
