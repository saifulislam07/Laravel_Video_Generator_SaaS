<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditService
{
    /**
     * Spend credits, recording a negative transaction. Row-locked so
     * concurrent renders can't overspend.
     *
     * @param  array<string, mixed>  $meta
     *
     * @throws InsufficientCreditsException
     */
    public function charge(User $user, int $amount, string $reason, array $meta = []): CreditTransaction
    {
        $amount = abs($amount);

        return DB::transaction(function () use ($user, $amount, $reason, $meta) {
            $locked = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->credits < $amount) {
                throw new InsufficientCreditsException();
            }

            $locked->decrement('credits', $amount);
            $user->setAttribute('credits', $locked->credits);

            return $this->log($locked, -$amount, $reason, $meta);
        });
    }

    /**
     * Grant credits (e.g. after a paid order), recording a positive transaction.
     *
     * @param  array<string, mixed>  $meta
     */
    public function grant(User $user, int $amount, string $reason, array $meta = []): CreditTransaction
    {
        $amount = abs($amount);

        return DB::transaction(function () use ($user, $amount, $reason, $meta) {
            $locked = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $locked->increment('credits', $amount);
            $user->setAttribute('credits', $locked->credits);

            return $this->log($locked, $amount, $reason, $meta);
        });
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function log(User $user, int $amount, string $reason, array $meta): CreditTransaction
    {
        return $user->creditTransactions()->create([
            'amount' => $amount,
            'balance_after' => $user->credits,
            'reason' => $reason,
            'meta' => $meta ?: null,
        ]);
    }
}
