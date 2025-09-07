<?php

namespace App\Observers;

use App\Models\SaleInventory;

class SaleInventoryObserver
{
    /**
     * Handle the SaleInventory "created" event.
     */
    public function created(SaleInventory $saleInventory): void
    {
        //
    }

    /**
     * Handle the SaleInventory "updated" event.
     */
    public function updated(SaleInventory $saleInventory): void
    {
        //
    }

    /**
     * Handle the SaleInventory "deleted" event.
     */
    public function deleted(SaleInventory $saleInventory): void
    {
        //
    }

    /**
     * Handle the SaleInventory "restored" event.
     */
    public function restored(SaleInventory $saleInventory): void
    {
        //
    }

    /**
     * Handle the SaleInventory "force deleted" event.
     */
    public function forceDeleted(SaleInventory $saleInventory): void
    {
        //
    }
}
