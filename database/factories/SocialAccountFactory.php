<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => SocialAccount::PROVIDER_FACEBOOK_PAGE,
            'provider_account_id' => (string) fake()->numerify('##########'),
            'name' => fake()->company(),
            'access_token' => 'page-token-'.fake()->uuid(),
            'token_expires_at' => now()->addDays(60),
        ];
    }

    public function instagram(): static
    {
        return $this->state(['provider' => SocialAccount::PROVIDER_INSTAGRAM]);
    }
}
