<?php

namespace App\Services\Billing;

use App\Models\CreditOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * SSLCommerz — hosted checkout (API v4) + validation API.
 *
 * @see https://developer.sslcommerz.com/doc/v4/
 */
class SslcommerzGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'sslcommerz';
    }

    protected function requiredKeys(): array
    {
        return ['store_id', 'store_password'];
    }

    protected function chargeLive(CreditOrder $order): string
    {
        $ref = $this->merchantReference($order);
        $callback = fn (string $result) => route('billing.callback', [
            'gateway' => 'sslcommerz', 'order' => $order->id, 'result' => $result,
        ]);

        $response = Http::asForm()->timeout(30)->post(
            $this->config('base_url').'/gwprocess/v4/api.php',
            [
                'store_id' => $this->config('store_id'),
                'store_passwd' => $this->config('store_password'),
                'total_amount' => $order->amount,
                'currency' => $order->currency,
                'tran_id' => $ref,
                'success_url' => $callback('success'),
                'fail_url' => $callback('fail'),
                'cancel_url' => $callback('cancel'),
                'ipn_url' => route('billing.callback', ['gateway' => 'sslcommerz', 'order' => $order->id, 'result' => 'ipn']),
                'cus_name' => $order->user->name,
                'cus_email' => $order->user->email,
                'cus_phone' => 'N/A',
                'cus_add1' => 'N/A',
                'shipping_method' => 'NO',
                'product_name' => 'Render credits · '.$order->package_key,
                'product_category' => 'digital',
                'product_profile' => 'non-physical-goods',
            ],
        );

        $data = $response->json();

        if ($response->failed() || ($data['status'] ?? null) !== 'SUCCESS' || blank($data['GatewayPageURL'] ?? null)) {
            Log::warning('SSLCommerz session failed', ['order' => $order->id, 'body' => $data]);
            throw new RuntimeException('SSLCommerz payment could not be started.');
        }

        return $data['GatewayPageURL'];
    }

    protected function confirmLive(CreditOrder $order, array $params): bool
    {
        if (($params['result'] ?? null) === 'cancel' || ($params['status'] ?? null) === 'FAILED') {
            return false;
        }

        $valId = $params['val_id'] ?? null;

        if (blank($valId)) {
            return false;
        }

        $data = Http::acceptJson()->timeout(30)->get(
            $this->config('base_url').'/validator/api/validationserverAPI.php',
            [
                'val_id' => $valId,
                'store_id' => $this->config('store_id'),
                'store_passwd' => $this->config('store_password'),
                'format' => 'json',
            ],
        )->json();

        return in_array($data['status'] ?? null, ['VALID', 'VALIDATED'], true)
            && ($data['tran_id'] ?? null) === $this->merchantReference($order)
            && (float) ($data['amount'] ?? 0) === (float) $order->amount
            && ($data['currency'] ?? null) === $order->currency;
    }
}
