<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\VideoRender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoRender>
 */
class VideoRenderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'shotstack_render_id' => fake()->uuid(),
            'status' => VideoRender::STATUS_QUEUED,
            'output_url' => null,
            'error_message' => null,
        ];
    }

    public function done(): static
    {
        return $this->state([
            'status' => VideoRender::STATUS_DONE,
            'output_url' => 'https://cdn.shotstack.io/'.fake()->uuid().'.mp4',
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => VideoRender::STATUS_FAILED,
            'error_message' => 'Render failed: '.fake()->sentence(),
        ]);
    }
}
