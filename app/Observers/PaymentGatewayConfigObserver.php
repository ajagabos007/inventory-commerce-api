<?php

namespace App\Observers;

use App\Models\PaymentGatewayConfig;

class PaymentGatewayConfigObserver
{
    /**
     * Handle the PaymentGatewayConfig "created" event.
     */
    public function created(PaymentGatewayConfig $paymentGatewayConfig): void
    {
        //
    }

    /**
     * Handle the PaymentGatewayConfig "updated" event.
     */
    public function updated(PaymentGatewayConfig $paymentGatewayConfig): void
    {
        //
    }

    /**
     * Handle the PaymentGatewayConfig "deleted" event.
     */
    public function deleted(PaymentGatewayConfig $paymentGatewayConfig): void
    {
        //
    }

    /**
     * Handle the PaymentGatewayConfig "restored" event.
     */
    public function restored(PaymentGatewayConfig $paymentGatewayConfig): void
    {
        //
    }

    /**
     * Handle the PaymentGatewayConfig "force deleted" event.
     */
    public function forceDeleted(PaymentGatewayConfig $paymentGatewayConfig): void
    {
        //
    }
}
