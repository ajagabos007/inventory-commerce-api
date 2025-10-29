<?php
return [
    'default_mode' => env('PAYMENT_DEFAULT_MODE', 'sandbox'),

    'gateways' => [
        'paystack' => [
            'webhook_url' => env('APP_URL') . '/api/webhooks/payment/paystack',
        ],
        'flutterwave' => [
            'webhook_url' => env('APP_URL') . '/api/webhooks/payment/flutterwave',
        ],
    ],
];
