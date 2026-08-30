<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'status' => Project::STATUS_DRAFT,
        ];
    }

    public function rendering(): static
    {
        return $this->state(['status' => Project::STATUS_RENDERING]);
    }

    public function completed(): static
    {
        return $this->state(['status' => Project::STATUS_COMPLETED]);
    }

    public function failed(): static
    {
        return $this->state(['status' => Project::STATUS_FAILED]);
    }
}
