<?php

namespace App\Observers;

use App\Models\StockTransfer;

class StockTransferObserver
{
    /**
     * Handle the StockTransfer "created" event.
     */
    public function creating(StockTransfer $stockTransfer): void
    {
        if (empty($stockTransfer->reference_no)) {
            $stockTransfer->reference_no = StockTransfer::generateReferenceNo();
        }

        if (auth()->check()) {
            $user = auth()->user();
            $stockTransfer->sender_id = empty($stockTransfer->sender_id) ? $user->id : $stockTransfer->sender_id;
            $stockTransfer->from_store_id = empty($stockTransfer->from_store_id) && ! empty($user->staff->store_id) ? $user->staff->store_id : $stockTransfer->from_store_id;
        }
    }

    /**
     * Handle the StockTransfer "created" event.
     */
    public function created(StockTransfer $stockTransfer): void {}

    /**
     * Handle the StockTransfer "updating" event.
     */
    public function updating(StockTransfer $stockTransfer): void
    {
        if (($stockTransfer->received_at || $stockTransfer->rejected_at) && empty($stockTransfer->receiver_id) && auth()->check()) {
            $stockTransfer->receiver_id = auth()->id();
        }
    }

    /**
     * Handle the StockTransfer "updated" event.
     */
    public function updated(StockTransfer $stockTransfer): void {}

    /**
     * Handle the StockTransfer "deleted" event.
     */
    public function deleted(StockTransfer $stockTransfer): void
    {
        //
    }

    /**
     * Handle the StockTransfer "restored" event.
     */
    public function restored(StockTransfer $stockTransfer): void
    {
        //
    }

    /**
     * Handle the StockTransfer "force deleted" event.
     */
    public function forceDeleted(StockTransfer $stockTransfer): void
    {
        //
    }
}
