<?php

namespace App\Observers;

use App\Models\Sale;
use App\Models\Staff;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class StaffObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Sale "created" event.
     */
    public function creating(Staff $staff): void
    {
        if (blank($staff->staff_no)) {
            $staff->staff_no = Staff::generateStaffNo();
        }
    }

    /**
     * Handle the Staff "created" event.
     */
    public function created(Staff $staff): void {}

    /**
     * Handle the Staff "updated" event.
     */
    public function updated(Staff $staff): void {}

    /**
     * Handle the Staff "deleted" event.
     */
    public function deleted(Staff $staff): void
    {
        $staff->user->delete();
    }

    /**
     * Handle the Staff "restored" event.
     */
    public function restored(Staff $staff): void
    {
        //
    }

    /**
     * Handle the Staff "force deleted" event.
     */
    public function forceDeleted(Staff $staff): void
    {
        //
    }
}
