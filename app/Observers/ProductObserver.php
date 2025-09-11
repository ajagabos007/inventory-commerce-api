<?php

namespace App\Observers;

use App\Models\Product;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function creating(Product $item): void {}

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $item): void {}

    /**
     * Handle the Product "updating" event.
     */
    public function updating(Product $item): void {}

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $item): void {}

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $item): void
    {
        //
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $item): void
    {
        //
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $item): void
    {
        //
    }
}
