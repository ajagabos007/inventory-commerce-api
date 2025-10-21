<?php

namespace App\Observers;

use App\Models\Inventory;
use function Illuminate\Support\defer;

class InventoryObserver
{
    /**
     * Handle the Inventory "created" event.
     */
    public function created(Inventory $inventory): void
    {
        defer(function () use ($inventory) {
           foreach($inventory->productVariants as $variant){
               $variant->updateAvailableQuantity();
               $variant->product->updateAvailableQuantity();
           }
        });
    }

    /**
     * Handle the Inventory "updated" event.
     */
    public function updated(Inventory $inventory): void
    {

        defer(function () use ($inventory) {
            if($inventory->wasChanged(['quantity'])){
                foreach($inventory->productVariants as $variant){
                    $variant->updateAvailableQuantity();
                    $variant->product->updateAvailableQuantity();
                }
            }
        });
    }

    /**
     * Handle the Inventory "deleted" event.
     */
    public function deleted(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "restored" event.
     */
    public function restored(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "force deleted" event.
     */
    public function forceDeleted(Inventory $inventory): void
    {
        //
    }
}
