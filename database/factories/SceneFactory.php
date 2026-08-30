<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scene>
 */
class SceneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'order' => 0,
            'background_image_path' => 'backgrounds/'.fake()->uuid().'.jpg',
            'dialogue_text' => fake()->sentence(),
            'duration_seconds' => fake()->numberBetween(3, 8),
        ];
    }
}
