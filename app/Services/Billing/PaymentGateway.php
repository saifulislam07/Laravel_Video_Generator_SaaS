<?php

namespace App\Services\Billing;

use App\Models\CreditOrder;

interface PaymentGateway
{
    /** The config key / identifier for this gateway (e.g. "bkash"). */
    public function key(): string;

    /** True when live credentials are present; false → mock checkout is used. */
    public function isConfigured(): bool;

    /**
     * Start a payment for the order and return the URL to redirect the
     * customer to (the gateway's hosted page, or the local mock page).
     */
    public function createCharge(CreditOrder $order): string;

    /**
     * Finalise a payment from the gateway's return/callback parameters.
     * Returns true only when the money has actually been captured.
     *
     * @param  array<string, mixed>  $params
     */
    public function confirm(CreditOrder $order, array $params): bool;
}
