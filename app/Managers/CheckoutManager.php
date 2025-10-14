<?php

namespace App\Managers;

class CheckoutManager
{
    const DEFAULT_INSTANCE = 'order-summary';

    public static function clearCheckoutSession()
    {
        session()->forget(Self::DEFAULT_INSTANCE);
    }
}
