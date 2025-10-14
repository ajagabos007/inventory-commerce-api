<?php

use Illuminate\Support\Str;

if (! function_exists('current_store')) {

    function current_store()
    {
        return app()->bound('currentStore') ? app('currentStore') : null;
    }
}

if (! function_exists('generateGatewayKeys')) {
    function generateGatewayKeys(string $gateway = 'generic', bool $isLive = false): array
    {
        $prefix = match (strtolower($gateway)) {
            'stripe' => $isLive ? ['sk_live_', 'pk_live_'] : ['sk_test_', 'pk_test_'],
            'paystack' => $isLive ? ['sk_live_', 'pk_live_'] : ['sk_test_', 'pk_test_'],
            'flutterwave' => $isLive ? ['FLWSECK_LIVE-', 'FLWPUBK_LIVE-'] : ['FLWSECK_TEST-', 'FLWPUBK_TEST-'],
            default => ['secret_', 'public_'],
        };

        return [
            'secret_key' => $prefix[0].Str::upper(Str::random(32)),
            'public_key' => $prefix[1].Str::upper(Str::random(32)),
            'hash_key' => Str::upper(Str::random(15)),
        ];
    }
}
