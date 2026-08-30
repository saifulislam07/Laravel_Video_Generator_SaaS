<?php

namespace App\Services\Billing;

use App\Models\CreditOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * bKash PGW — Tokenized Checkout (v1.2.0-beta).
 *
 * @see https://developer.bka.sh/docs/tokenized-checkout-overview
 */
class BkashGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'bkash';
    }

    protected function requiredKeys(): array
    {
        return ['app_key', 'app_secret', 'username', 'password'];
    }

    protected function chargeLive(CreditOrder $order): string
    {
        $response = $this->request()->post($this->url('create'), [
            'mode' => '0011',
            'payerReference' => (string) $order->user_id,
            'callbackURL' => route('billing.callback', ['gateway' => 'bkash', 'order' => $order->id]),
            'amount' => (string) $order->amount,
            'currency' => $order->currency,
            'intent' => 'sale',
            'merchantInvoiceNumber' => $this->merchantReference($order),
        ]);

        $data = $response->json();

        if ($response->failed() || ($data['statusCode'] ?? null) !== '0000' || blank($data['bkashURL'] ?? null)) {
            Log::warning('bKash create payment failed', ['order' => $order->id, 'body' => $data]);
            throw new RuntimeException('bKash payment could not be started.');
        }

        $order->update(['meta' => [...(array) $order->meta, 'payment_id' => $data['paymentID']]]);

        return $data['bkashURL'];
    }

    protected function confirmLive(CreditOrder $order, array $params): bool
    {
        if (($params['status'] ?? null) !== 'success') {
            return false;
        }

        $paymentId = $params['paymentID'] ?? ($order->meta['payment_id'] ?? null);

        if (blank($paymentId)) {
            return false;
        }

        $data = $this->request()->post($this->url('execute'), ['paymentID' => $paymentId])->json();

        // Execute can time out under load — fall back to the status query.
        if (($data['statusCode'] ?? null) !== '0000') {
            $data = $this->request()->post($this->url('payment/status'), ['paymentID' => $paymentId])->json();
        }

        $ok = ($data['transactionStatus'] ?? null) === 'Completed'
            && (float) ($data['amount'] ?? 0) === (float) $order->amount;

        if ($ok) {
            $order->update(['meta' => [...(array) $order->meta, 'trx_id' => $data['trxID'] ?? null]]);
        }

        return $ok;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => $this->idToken(),
            'X-APP-Key' => $this->config('app_key'),
        ])->acceptJson()->asJson()->timeout(30);
    }

    private function idToken(): string
    {
        return Cache::remember('bkash:id_token', now()->addMinutes(50), function () {
            $data = Http::withHeaders([
                'username' => $this->config('username'),
                'password' => $this->config('password'),
            ])->acceptJson()->asJson()->timeout(30)
                ->post($this->url('token/grant'), [
                    'app_key' => $this->config('app_key'),
                    'app_secret' => $this->config('app_secret'),
                ])->json();

            return $data['id_token'] ?? throw new RuntimeException('bKash token grant failed.');
        });
    }

    private function url(string $path): string
    {
        return rtrim($this->config('base_url'), '/')."/{$path}";
    }
}
