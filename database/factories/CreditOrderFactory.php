<?php

namespace Database\Factories;

use App\Models\CreditOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditOrder>
 */
class CreditOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'package_key' => 'starter',
            'credits' => 10,
            'amount' => 200,
            'currency' => 'BDT',
            'gateway' => 'bkash',
            'status' => CreditOrder::STATUS_PENDING,
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => CreditOrder::STATUS_PAID, 'paid_at' => now()]);
    }
}
