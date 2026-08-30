<?php

namespace App\Services\Billing;

use App\Models\CreditOrder;

/**
 * Shared behaviour for the not-yet-live gateway integrations.
 *
 * Real integration TODO (per gateway):
 *  - createCharge(): call the gateway "create payment" endpoint with the order
 *    amount/currency/reference, persist the returned payment id on
 *    $order->gateway_ref, and return the gateway's hosted checkout URL.
 *  - verify(): call the gateway "execute/validate payment" endpoint (or check
 *    the IPN signature) and confirm amount + reference match $order.
 */
abstract class AbstractStubGateway implements PaymentGateway
{
    /**
     * For now every gateway routes the customer to our local mock checkout
     * page instead of a real hosted payment page.
     */
    public function createCharge(CreditOrder $order): string
    {
        $order->update([
            'gateway_ref' => strtoupper($this->key()).'-'.str()->random(12),
        ]);

        return route('billing.mock', $order);
    }

    /**
     * The mock checkout posts back {status: "success"|"failed"}.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(CreditOrder $order, array $payload): bool
    {
        return ($payload['status'] ?? null) === 'success';
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("billing.gateways.{$this->key()}.{$key}", $default);
    }
}
