<?php

namespace App\Observers;

use App\Models\Store;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class StoreObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Store "created" event.
     */
    public function created(Store $store): void
    {
        if (Store::count() == 0) {
            $store->markAsWarehouse();
        } elseif ($store->is_warehouse) {
            $store->markAsWarehouse();
        }

        $store->updateManagerAsStaff();
    }

    /**
     * Handle the Store "updated" event.
     */
    public function updated(Store $store): void
    {
        if ($store->is_warehouse) {
            $store->markAsWarehouse();
        }

        $store->updateManagerAsStaff();
    }

    /**
     * Handle the Store "deleted" event.
     */
    public function deleted(Store $store): void
    {
        //
    }

    /**
     * Handle the Store "restored" event.
     */
    public function restored(Store $store): void
    {
        //
    }

    /**
     * Handle the Store "force deleted" event.
     */
    public function forceDeleted(Store $store): void
    {
        //
    }
}
