<?php

return [
    'default_mode' => env('PAYMENT_DEFAULT_MODE', 'sandbox'),

    'gateways' => [
        'paystack' => [
            'webhook_url' => env('APP_URL').'/api/webhooks/payment/paystack',
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
            'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),

        ],
        'flutterwave' => [
            'webhook_url' => env('APP_URL').'/api/webhooks/payment/flutterwave',
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
            'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
        ],
    ],
];
