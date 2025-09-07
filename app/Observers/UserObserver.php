<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

use function Illuminate\Support\defer;

class UserObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        defer(function () {});
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        defer(function () use ($user) {
            if ($user->customer) {
                $user->customer->name = $user->full_name;
                $user->customer->email = $user->email;
                $user->customer->phone_number = $user->phone_number;
                $user->customer->saveQuietly();
            }
        });
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
