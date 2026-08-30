<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Free tier
    |--------------------------------------------------------------------------
    | Credits granted to a brand new account (also the DB default on users).
    */
    'signup_credits' => 5,

    /*
    |--------------------------------------------------------------------------
    | Credit cost per action
    |--------------------------------------------------------------------------
    */
    'cost' => [
        'video_render' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchasable packages
    |--------------------------------------------------------------------------
    | `amount` is in whole BDT. Keyed by a stable slug used in URLs/orders.
    */
    'currency' => 'BDT',

    'packages' => [
        'starter' => ['name' => 'Starter', 'credits' => 10, 'amount' => 200],
        'creator' => ['name' => 'Creator', 'credits' => 50, 'amount' => 800, 'popular' => true],
        'studio' => ['name' => 'Studio', 'credits' => 100, 'amount' => 1400],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment gateways
    |--------------------------------------------------------------------------
    | Structure only — no live credentials are wired yet. `driver` maps to a
    | class implementing App\Services\Billing\PaymentGateway.
    */
    'default_gateway' => 'bkash',

    'gateways' => [
        'bkash' => [
            'driver' => \App\Services\Billing\BkashGateway::class,
            'label' => 'bKash',
            'app_key' => env('BKASH_APP_KEY'),
            'app_secret' => env('BKASH_APP_SECRET'),
            'username' => env('BKASH_USERNAME'),
            'password' => env('BKASH_PASSWORD'),
            'sandbox' => env('BKASH_SANDBOX', true),
            'base_url' => env('BKASH_SANDBOX', true)
                ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout'
                : 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout',
        ],
        'sslcommerz' => [
            'driver' => \App\Services\Billing\SslcommerzGateway::class,
            'label' => 'SSLCommerz',
            'store_id' => env('SSLCZ_STORE_ID'),
            'store_password' => env('SSLCZ_STORE_PASSWORD'),
            'sandbox' => env('SSLCZ_SANDBOX', true),
            'base_url' => env('SSLCZ_SANDBOX', true)
                ? 'https://sandbox.sslcommerz.com'
                : 'https://securepay.sslcommerz.com',
        ],
    ],
];
