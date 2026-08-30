<?php

namespace App\Services\Billing;

use App\Models\CreditOrder;

interface PaymentGateway
{
    /**
     * The config key / identifier for this gateway (e.g. "bkash").
     */
    public function key(): string;

    /**
     * Start a payment for the given order and return the URL the customer
     * should be redirected to in order to complete it.
     */
    public function createCharge(CreditOrder $order): string;

    /**
     * Verify a return/callback payload from the gateway. Returns true when the
     * payment is confirmed as successful.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(CreditOrder $order, array $payload): bool;
}
