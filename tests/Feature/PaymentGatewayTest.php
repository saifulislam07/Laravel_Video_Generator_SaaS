<?php

use App\Models\CreditOrder;
use App\Models\User;
use App\Services\Billing\BillingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

function configureBkash(): void
{
    config()->set('billing.gateways.bkash', [
        'driver' => \App\Services\Billing\BkashGateway::class,
        'label' => 'bKash',
        'app_key' => 'app-key', 'app_secret' => 'app-secret',
        'username' => 'user', 'password' => 'pass',
        'sandbox' => true,
        'base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout',
    ]);
}

function configureSsl(): void
{
    config()->set('billing.gateways.sslcommerz', [
        'driver' => \App\Services\Billing\SslcommerzGateway::class,
        'label' => 'SSLCommerz',
        'store_id' => 'store', 'store_password' => 'pw', 'sandbox' => true,
        'base_url' => 'https://sandbox.sslcommerz.com',
    ]);
}

afterEach(fn () => Cache::forget('bkash:id_token'));

describe('bKash', function () {
    it('falls back to mock checkout when not configured', function () {
        config()->set('billing.gateways.bkash.app_key', null);
        $user = User::factory()->create();

        $result = app(BillingService::class)->startPurchase($user, 'starter', 'bkash');

        expect($result['checkoutUrl'])->toContain('/billing/checkout/');
    });

    it('creates a live payment and returns the bkash url', function () {
        configureBkash();
        Http::fake([
            '*token/grant' => Http::response(['id_token' => 'tok-123']),
            '*checkout/create' => Http::response([
                'statusCode' => '0000', 'paymentID' => 'pay-1', 'bkashURL' => 'https://sandbox.bka.sh/pay/pay-1',
            ]),
        ]);

        $result = app(BillingService::class)->startPurchase(User::factory()->create(), 'creator', 'bkash');

        expect($result['checkoutUrl'])->toBe('https://sandbox.bka.sh/pay/pay-1')
            ->and($result['order']->fresh()->meta['payment_id'])->toBe('pay-1');

        Http::assertSent(fn ($r) => $r->hasHeader('X-APP-Key', 'app-key')
            && str_contains($r->url(), 'checkout/create'));
    });

    it('executes and grants credits on a successful callback', function () {
        configureBkash();
        $user = User::factory()->credits(0)->create();
        $order = CreditOrder::factory()->for($user)->create([
            'gateway' => 'bkash', 'credits' => 50, 'amount' => 800, 'meta' => ['payment_id' => 'pay-9'],
        ]);

        Http::fake([
            '*token/grant' => Http::response(['id_token' => 'tok']),
            '*checkout/execute' => Http::response([
                'statusCode' => '0000', 'transactionStatus' => 'Completed', 'trxID' => 'TRX9', 'amount' => '800',
            ]),
        ]);

        app(BillingService::class)->completePurchase($order, ['status' => 'success', 'paymentID' => 'pay-9']);

        expect($order->fresh())->status->toBe(CreditOrder::STATUS_PAID)
            ->and($order->fresh()->meta['trx_id'])->toBe('TRX9')
            ->and($user->fresh()->credits)->toBe(50);
    });

    it('does not grant when the customer cancels', function () {
        configureBkash();
        $order = CreditOrder::factory()->create(['gateway' => 'bkash']);

        app(BillingService::class)->completePurchase($order, ['status' => 'cancel']);

        expect($order->fresh()->status)->toBe(CreditOrder::STATUS_FAILED);
        Http::assertNothingSent();
    });

    it('rejects an amount mismatch from execute', function () {
        configureBkash();
        $order = CreditOrder::factory()->create(['gateway' => 'bkash', 'amount' => 800, 'meta' => ['payment_id' => 'p']]);
        Http::fake([
            '*token/grant' => Http::response(['id_token' => 't']),
            '*checkout/execute' => Http::response(['statusCode' => '0000', 'transactionStatus' => 'Completed', 'amount' => '10']),
            '*payment/status' => Http::response(['transactionStatus' => 'Completed', 'amount' => '10']),
        ]);

        app(BillingService::class)->completePurchase($order, ['status' => 'success']);

        expect($order->fresh()->status)->toBe(CreditOrder::STATUS_FAILED);
    });
});

describe('SSLCommerz', function () {
    it('creates a session and returns the gateway page url', function () {
        configureSsl();
        Http::fake([
            '*gwprocess/v4/api.php' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/abc']),
        ]);

        $result = app(BillingService::class)->startPurchase(User::factory()->create(), 'studio', 'sslcommerz');

        expect($result['checkoutUrl'])->toBe('https://sandbox.sslcommerz.com/pay/abc');
    });

    it('validates and grants credits', function () {
        configureSsl();
        $user = User::factory()->credits(1)->create();
        $order = CreditOrder::factory()->for($user)->create([
            'gateway' => 'sslcommerz', 'credits' => 100, 'amount' => 1400, 'currency' => 'BDT',
        ]);
        $order->update(['gateway_ref' => 'VG-'.$order->id]);

        Http::fake([
            '*validationserverAPI.php*' => Http::response([
                'status' => 'VALID', 'tran_id' => 'VG-'.$order->id, 'amount' => '1400', 'currency' => 'BDT',
            ]),
        ]);

        app(BillingService::class)->completePurchase($order->fresh(), ['result' => 'success', 'val_id' => 'v1']);

        expect($order->fresh()->status)->toBe(CreditOrder::STATUS_PAID)
            ->and($user->fresh()->credits)->toBe(101);
    });

    it('rejects an invalid validation response', function () {
        configureSsl();
        $order = CreditOrder::factory()->create(['gateway' => 'sslcommerz', 'amount' => 1400]);
        $order->update(['gateway_ref' => 'VG-'.$order->id]);
        Http::fake(['*validationserverAPI.php*' => Http::response(['status' => 'INVALID_TRANSACTION'])]);

        app(BillingService::class)->completePurchase($order->fresh(), ['result' => 'success', 'val_id' => 'v1']);

        expect($order->fresh()->status)->toBe(CreditOrder::STATUS_FAILED);
    });
});

describe('callback route', function () {
    it('grants credits from a bkash redirect', function () {
        configureBkash();
        $user = User::factory()->credits(0)->create();
        $order = CreditOrder::factory()->for($user)->create([
            'gateway' => 'bkash', 'credits' => 10, 'amount' => 200, 'meta' => ['payment_id' => 'pp'],
        ]);
        Http::fake([
            '*token/grant' => Http::response(['id_token' => 't']),
            '*checkout/execute' => Http::response(['statusCode' => '0000', 'transactionStatus' => 'Completed', 'amount' => '200']),
        ]);

        actingAs($user)
            ->get(route('billing.callback', ['gateway' => 'bkash', 'order' => $order->id, 'status' => 'success', 'paymentID' => 'pp']))
            ->assertRedirect(route('dashboard'));

        expect($user->fresh()->credits)->toBe(10);
    });

    it('404s for an unknown gateway', function () {
        actingAs(User::factory()->create())
            ->get(route('billing.callback', ['gateway' => 'paypal', 'order' => 1]))
            ->assertNotFound();
    });
});
