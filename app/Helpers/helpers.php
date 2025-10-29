<?php

use App\Models\Store;
use Illuminate\Support\Str;

if (! function_exists('current_store')) {

    function current_store()
    {
        return app()->bound('currentStore') ? app('currentStore') : null;
    }
}

if (! function_exists('set_current_store')) {

    /**
     * Set the current store in the application container.
     *
     * @param  \App\Models\Store|int|null  $store
     */
    function set_current_store($store): void
    {
        if (! $store instanceof Store) {
            $store = \App\Models\Store::find($store);
        }

        if ($store instanceof \App\Models\Store) {
            app()->instance('currentStore', $store);
        } else {
            app()->forgetInstance('currentStore');
        }
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
