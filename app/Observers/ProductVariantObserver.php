<?php

namespace App\Observers;

use App\Models\ProductVariant;

class ProductVariantObserver
{
    /**
     * Handle the Product "saving" event.
     */
    public function saving(ProductVariant $productVariant): void
    {
        if(!is_bool($productVariant->is_serialized)){
            $productVariant->is_serialized = $productVariant->product->is_serialized;
        }

        if (blank($productVariant->sku)) {
            $productVariant->sku = ProductVariant::generateSKU();
        }

        if (blank($productVariant->barcode)) {
            $productVariant->barcode = $productVariant->generateBarcode();
        }
    }

    /**
     * Handle the ProductVariant "created" event.
     */
    public function created(ProductVariant $productVariant): void
    {
        $product = $productVariant->product;
        $product->updateDisplayPrices();
    }

    /**
     * Handle the ProductVariant "updated" event.
     */
    public function updated(ProductVariant $productVariant): void
    {
        if ($productVariant->wasChanged('price') || $productVariant->wasChanged('compare_price')) {
            $product = $productVariant->product;
            $product->updateDisplayPrices();
        }
    }

    /**
     * Handle the ProductVariant "saved" event.
     */
    public function saved(ProductVariant $productVariant): void {}

    /**
     * Handle the ProductVariant "deleted" event.
     */
    public function deleted(ProductVariant $productVariant): void
    {
        $product = $productVariant->product;
        $product->updateDisplayPrices();
    }

    /**
     * Handle the ProductVariant "restored" event.
     */
    public function restored(ProductVariant $productVariant): void
    {
        //
    }

    /**
     * Handle the ProductVariant "force deleted" event.
     */
    public function forceDeleted(ProductVariant $productVariant): void
    {
        //
    }
}
