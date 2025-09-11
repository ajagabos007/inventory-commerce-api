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

        $min_price = $product->variants()->min('price');
        $max_price = $product->variants()->max('price');

        $product->display_price = $min_price == $max_price ? $min_price : $min_price.'-'.$max_price;

        $min_compare_price = $product->variants()->min('compare_price');
        $max_compare_price = $product->variants()->max('compare_price');

        $product->display_compare_price = $min_compare_price == $max_compare_price ? $min_compare_price : $min_compare_price.'-'.$max_compare_price;

        $product->saveQuietly();

    }

    /**
     * Handle the ProductVariant "updated" event.
     */
    public function updated(ProductVariant $productVariant): void
    {
        $product = $productVariant->product;

        if ($productVariant->wasChanged('price')) {
            $min_price = $product->variants()->min('price');
            $max_price = $product->variants()->max('price');
            $product->display_price = $min_price == $max_price ? $min_price : $min_price.'-'.$max_price;
        }

        if ($productVariant->wasChanged('compare_price')) {
            $min_compare_price = $product->variants()->min('compare_price');
            $max_compare_price = $product->variants()->max('compare_price');
            $product->display_compare_price = $min_compare_price == $max_compare_price ? $min_compare_price : $min_compare_price.'-'.$max_compare_price;
        }

        if ($product->isDirty(['price', 'compare_price'])) {
            $product->saveQuietly();
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
        //
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
