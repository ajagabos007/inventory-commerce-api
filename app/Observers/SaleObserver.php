<?php

namespace App\Observers;

use App\Models\Sale;

class SaleObserver
{
    /**
     * Handle the Sale "created" event.
     */
    public function creating(Sale $sale): void
    {
        if (blank($sale->cashier_staff_id) && auth()->check() && auth()) {
            $sale->cashier_staff_id = auth()->user()->staff?->id;
        }

        if (! $sale->invoice_number) {
            $sale->invoice_number = Sale::generateInvoiceNumber();
        }
    }

    /**
     * Handle the Sale "created" event.
     */
    public function created(Sale $sale): void {}

    /**
     * Handle the Sale "updated" event.
     */
    public function updated(Sale $sale): void
    {
        //
    }

    /**
     * Handle the Sale "deleted" event.
     */
    public function deleted(Sale $sale): void
    {
        //
    }

    /**
     * Handle the Sale "restored" event.
     */
    public function restored(Sale $sale): void
    {
        //
    }

    /**
     * Handle the Sale "force deleted" event.
     */
    public function forceDeleted(Sale $sale): void
    {
        //
    }
}
