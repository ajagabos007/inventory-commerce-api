<?php

namespace App\Observers;

use App\Models\DailyGoldPrice;

class DailyGoldPriceObserver
{
    /**
     * Handle the DailyGoldPrice "created" event.
     */
    public function creating(DailyGoldPrice $dailyGoldPrice): void
    {
        if ($dailyGoldPrice->recorded_on === null) {
            $dailyGoldPrice->recorded_on = now();
        }
    }

    /**
     * Handle the DailyGoldPrice "created" event.
     */
    public function created(DailyGoldPrice $dailyGoldPrice): void
    {
        //
    }

    /**
     * Handle the DailyGoldPrice "updated" event.
     */
    public function updated(DailyGoldPrice $dailyGoldPrice): void
    {
        //
    }

    /**
     * Handle the DailyGoldPrice "deleted" event.
     */
    public function deleted(DailyGoldPrice $dailyGoldPrice): void
    {
        //
    }

    /**
     * Handle the DailyGoldPrice "restored" event.
     */
    public function restored(DailyGoldPrice $dailyGoldPrice): void
    {
        //
    }

    /**
     * Handle the DailyGoldPrice "force deleted" event.
     */
    public function forceDeleted(DailyGoldPrice $dailyGoldPrice): void
    {
        //
    }
}
