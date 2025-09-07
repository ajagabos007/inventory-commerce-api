<?php

namespace App\Observers;

use App\Models\Discount;

class DiscountObserver
{
    /**
     * Handle the Discount "created" event.
     */
    public function created(Discount $discount): void
    {
        //
    }

    /**
     * Handle the Discount "updated" event.
     */
    public function updated(Discount $discount): void
    {
        //
    }

    /**
     * Handle the Discount "deleted" event.
     */
    public function deleted(Discount $discount): void
    {
        //
    }

    /**
     * Handle the Discount "restored" event.
     */
    public function restored(Discount $discount): void
    {
        //
    }

    /**
     * Handle the Discount "force deleted" event.
     */
    public function forceDeleted(Discount $discount): void
    {
        //
    }
}
