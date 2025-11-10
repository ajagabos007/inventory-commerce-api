<?php

namespace App\Listeners;

use App\Events\Order\OrderPaid;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Sale;
use App\Models\SaleInventory;
use Illuminate\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;


class SaleEventSubscriber implements ShouldQueue
{
    /**
     * Handle payment verification events.
     */
    public function handleOrderPaid(OrderPaid $event): void
    {
        $order = $event->order;
        $sale = Sale::query()
            ->where('metadata->order->id', $order->id)
            ->first();
        if (! $sale) {
            $sale = new Sale;
        }

        if ($order->user) {
            $sale->buyerable_id = $order->user->id;
            $sale->buyerable_type = get_class($order->user);
        }

        $order->refresh();

        $sale->total_price = $order->total_price;
        $sale->subtotal_price = $order->subtotal_price;
        $sale->tax = $order->tax;
        $sale->channel = 'ecommerce';
        $sale->discount_amount = $order->discount_amount;
        $sale->payment_method = $order->payment_method;
        $metadata = $sale->metadata ?? [];
        $metadata['order']['id'] = $order->id;
        $sale->metadata = $metadata;

        $sale->save();

        $saleInventories = [];

        foreach ($order->items as $item) {
            $options = $item->options ?? [];

            $itemType = data_get($options, 'itemable_type');
            $itemId = data_get($options, 'itemable_id');

            if ($itemType === Inventory::class && $itemId) {
                $saleInventories[] = [
                    'sale_id' => $sale->id,
                    'inventory_id' => $itemId,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total_price' => $item->price * $item->quantity,
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            }
        }

        if (! empty($saleInventories)) {
            SaleInventory::upsert(
                $saleInventories,
                ['sale_id', 'inventory_id'], // unique constraint
                ['quantity', 'price', 'total_price', 'updated_at'] // columns to update if exists
            );
        }

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

        if ($payable->payable instanceof Order) {
            $payable->payable->status = 'paid';
            $payable->payable->saveQuietly();
        }
    }

    /**
     * Register event listeners.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            OrderPaid::class,
            [self::class, 'handleOrderPaid']
        );
    }
}
