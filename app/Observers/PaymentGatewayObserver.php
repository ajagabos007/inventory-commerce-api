<?php

namespace App\Observers;

use App\Models\PaymentGateway;

class PaymentGatewayObserver
{
    /**
     * Handle the PaymentGateway "created" event.
     */
    public function created(PaymentGateway $paymentGateway): void
    {
        //
    }

    /**
     * Handle the PaymentGateway "updated" event.
     */
    public function updated(PaymentGateway $paymentGateway): void
    {
        //
    }

    /**
     * Handle the PaymentGateway "deleted" event.
     */
    public function deleted(PaymentGateway $paymentGateway): void
    {
        //
    }

    /**
     * Handle the PaymentGateway "restored" event.
     */
    public function restored(PaymentGateway $paymentGateway): void
    {
        //
    }

    /**
     * Handle the PaymentGateway "force deleted" event.
     */
    public function forceDeleted(PaymentGateway $paymentGateway): void
    {
        //
    }
}
