<?php

namespace App\Observers;

use App\Models\StockTransferInventory;

class StockTransferInventoryObserver
{
    /**
     * Handle the StockTransferInventory "created" event.
     */
    public function created(StockTransferInventory $stockTransferInventory): void
    {
        //
    }

    /**
     * Handle the StockTransferInventory "updated" event.
     */
    public function updated(StockTransferInventory $stockTransferInventory): void
    {
        //
    }

    /**
     * Handle the StockTransferInventory "deleted" event.
     */
    public function deleted(StockTransferInventory $stockTransferInventory): void
    {
        //
    }

    /**
     * Handle the StockTransferInventory "restored" event.
     */
    public function restored(StockTransferInventory $stockTransferInventory): void
    {
        //
    }

    /**
     * Handle the StockTransferInventory "force deleted" event.
     */
    public function forceDeleted(StockTransferInventory $stockTransferInventory): void
    {
        //
    }
}
