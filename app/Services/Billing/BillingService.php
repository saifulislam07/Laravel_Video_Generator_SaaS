<?php

namespace App\Services\Billing;

use App\Models\CreditOrder;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingService
{
    public function __construct(private readonly CreditService $credits) {}

    /**
     * @return array<string, array{name:string,credits:int,amount:int,popular?:bool}>
     */
    public function packages(): array
    {
        return config('billing.packages');
    }

    /**
     * @return array{name:string,credits:int,amount:int,popular?:bool}
     */
    public function package(string $key): array
    {
        return $this->packages()[$key]
            ?? throw new InvalidArgumentException("Unknown package [{$key}].");
    }

    public function gateway(string $key): PaymentGateway
    {
        $driver = config("billing.gateways.{$key}.driver")
            ?? throw new InvalidArgumentException("Unknown gateway [{$key}].");

        return app($driver);
    }

    /**
     * Create a pending order and hand back the checkout URL to redirect to.
     *
     * @return array{order: CreditOrder, checkoutUrl: string}
     */
    public function startPurchase(User $user, string $packageKey, string $gatewayKey): array
    {
        $package = $this->package($packageKey);
        $gateway = $this->gateway($gatewayKey);

        $order = $user->creditOrders()->create([
            'package_key' => $packageKey,
            'credits' => $package['credits'],
            'amount' => $package['amount'],
            'currency' => config('billing.currency', 'BDT'),
            'gateway' => $gatewayKey,
            'status' => CreditOrder::STATUS_PENDING,
        ]);

        return [
            'order' => $order,
            'checkoutUrl' => $gateway->createCharge($order),
        ];
    }

    /**
     * Confirm payment and top up the customer's credits. Idempotent.
     *
     * @param  array<string, mixed>  $payload
     */
    public function completePurchase(CreditOrder $order, array $payload = ['status' => 'success']): CreditOrder
    {
        if ($order->isPaid()) {
            return $order;
        }

        $gateway = $this->gateway($order->gateway);

        if (! $gateway->verify($order, $payload)) {
            $order->update(['status' => CreditOrder::STATUS_FAILED]);

            return $order;
        }

        return DB::transaction(function () use ($order) {
            $order->update([
                'status' => CreditOrder::STATUS_PAID,
                'paid_at' => now(),
            ]);

            $this->credits->grant($order->user, $order->credits, 'package_purchase', [
                'credit_order_id' => $order->id,
                'package_key' => $order->package_key,
            ]);

            return $order->fresh();
        });
    }

    public function cancel(CreditOrder $order): void
    {
        if (! $order->isPaid()) {
            $order->update(['status' => CreditOrder::STATUS_CANCELLED]);
        }
    }
}
