<?php

namespace App\Observers;

use App\Models\Scrape;

class ScrapeObserver
{
    /**
     * Handle the Scrape "creating" event.
     */
    public function creating(Scrape $scrape): void
    {
        if (! $scrape->staff_id) {
            $scrape->staff_id = auth()->user()?->staff?->id;
        }
    }

    /**
     * Handle the Scrape "created" event.
     */
    public function created(Scrape $scrape): void
    {
        //
    }

    /**
     * Handle the Scrape "updating" event.
     */
    public function updating(Scrape $scrape): void
    {
        $scrape->staff_id = auth()->user()?->staff?->id;

    }

    /**
     * Handle the Scrape "updated" event.
     */
    public function updated(Scrape $scrape): void
    {
        //
    }

    /**
     * Handle the Scrape "deleted" event.
     */
    public function deleted(Scrape $scrape): void
    {
        //
    }

    /**
     * Handle the Scrape "restored" event.
     */
    public function restored(Scrape $scrape): void
    {
        //
    }

    /**
     * Handle the Scrape "force deleted" event.
     */
    public function forceDeleted(Scrape $scrape): void
    {
        //
    }
}
