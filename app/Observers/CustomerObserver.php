<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\User;

class CustomerObserver
{
    /**
     * Handle the Sale "creating" event.
     */
    public function creating(Customer $customer): void
    {
        if (! $customer->user_id) {
            $user = User::where('email', $customer->email)
                ->where('phone_number', $customer->phone_number)
                ->first();
            // $user = !is_null($user) ? $user : User::where('email', $customer->email)->first();
            if ($user) {
                $customer->user_id = $user->id;
                $customer->name = $user->full_name;
                $customer->phone_number = $user->phone_number;
            }
        }
    }

    /**
     * Handle the Customer "created" event.
     */
    public function created(Customer $customer): void
    {
        //
    }

    /**
     * Handle the Customer "updated" event.
     */
    public function updated(Customer $customer): void
    {
        //
    }

    /**
     * Handle the Customer "deleted" event.
     */
    public function deleted(Customer $customer): void
    {
        //
    }

    /**
     * Handle the Customer "restored" event.
     */
    public function restored(Customer $customer): void
    {
        //
    }

    /**
     * Handle the Customer "force deleted" event.
     */
    public function forceDeleted(Customer $customer): void
    {
        //
    }
}
