<?php

namespace App\Observers;

use App\Models\Colour;

class ColourObserver
{
    /**
     * Handle the Colour "created" event.
     */
    public function created(Colour $colour): void
    {
        //
    }

    /**
     * Handle the Colour "updated" event.
     */
    public function updated(Colour $colour): void
    {
        //
    }

    /**
     * Handle the Colour "deleted" event.
     */
    public function deleted(Colour $colour): void
    {
        //
    }

    /**
     * Handle the Colour "restored" event.
     */
    public function restored(Colour $colour): void
    {
        //
    }

    /**
     * Handle the Colour "force deleted" event.
     */
    public function forceDeleted(Colour $colour): void
    {
        //
    }
}
