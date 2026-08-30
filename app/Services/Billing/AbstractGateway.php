<?php

namespace App\Services\Billing;

use App\Models\CreditOrder;

/**
 * Base class: real gateways implement chargeLive()/confirmLive(); when the
 * gateway is not configured, everything falls back to the local mock checkout
 * page so the app is fully usable in development.
 */
abstract class AbstractGateway implements PaymentGateway
{
    /** Config keys that must all be non-empty for the gateway to be "live". */
    abstract protected function requiredKeys(): array;

    abstract protected function chargeLive(CreditOrder $order): string;

    /** @param array<string, mixed> $params */
    abstract protected function confirmLive(CreditOrder $order, array $params): bool;

    public function isConfigured(): bool
    {
        foreach ($this->requiredKeys() as $key) {
            if (blank($this->config($key))) {
                return false;
            }
        }

        return true;
    }

    public function createCharge(CreditOrder $order): string
    {
        if (! $this->isConfigured()) {
            $order->update(['gateway_ref' => strtoupper($this->key()).'-MOCK-'.$order->id]);

            return route('billing.mock', $order);
        }

        return $this->chargeLive($order);
    }

    public function confirm(CreditOrder $order, array $params): bool
    {
        if (! $this->isConfigured()) {
            return ($params['status'] ?? null) === 'success';
        }

        return $this->confirmLive($order, $params);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("billing.gateways.{$this->key()}.{$key}", $default);
    }

    protected function merchantReference(CreditOrder $order): string
    {
        return $order->gateway_ref ?: 'VG-'.$order->id;
    }
}
