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
        $user = auth()->user();

        if (! blank($user)) {
            if (blank($sale->cashier_staff_id)) {
                $sale->cashier_staff_id = $user->staff?->id;
            }
            if (blank($sale->buyerable_id) || blank($sale->buyerable_type)) {
                $sale->buyerable_id = $user->id;
                $sale->buyerable_type = get_class($user);
            }
        }

        if (blank($sale->invoice_number)) {
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
     * Handle the Sale "saving" event.
     */
    public function saving(Sale $sale): void
    {
        if (blank($sale->invoice_number)) {
            $sale->invoice_number = Sale::generateInvoiceNumber();
        }

        if (blank($sale->barcode)) {
            $sale->barcode = $sale->generateBarcode();
        }
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
