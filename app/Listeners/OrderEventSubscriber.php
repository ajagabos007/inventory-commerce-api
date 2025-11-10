<?php

namespace App\Listeners;

use App\Events\Order\OrderPaid;
use App\Events\Payment\PaymentVerified;
use App\Models\Order;
use Illuminate\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
class OrderEventSubscriber implements ShouldQueue
{
    /**
     * Handle payment verification events.
     */
    public function handlePaymentVerified(PaymentVerified $event): void
    {
        $payment = $event->payment;

        $payment->payables()
            ->where('payable_type', Order::class)
            ->with('payable:id,status') // eager-load only necessary columns
            ->chunkById(500, function ($payables) use ($payment) {
                foreach ($payables as $payable) {
                    $this->verifyPayable($payable, $payment);
                }
            });
    }

    /**
     * Verify the payable and mark its order as paid.
     */
    protected function verifyPayable($payable, $payment): void
    {
        $payable->forceFill([
            'verified_at' => $payment->verified_at ?? now(),
            'verifier_id' => $payment->verifier_id ?? auth()->id(),
        ])->saveQuietly();

        if (! $payable->payable instanceof Order) {
            return;
        }

        //        if(!blank($payable->payable->status) && $payable->payable->status!="pending"){
        //            return;
        //        }

        $payable->payable->status = 'paid';
        $payable->payable->saveQuietly();

        OrderPaid::dispatch($payable->payable);
    }

    /**
     * Register event listeners.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            PaymentVerified::class,
            [self::class, 'handlePaymentVerified']
        );
    }
}
