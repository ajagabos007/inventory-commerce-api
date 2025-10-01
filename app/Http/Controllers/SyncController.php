<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SyncController
{
    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/sync/products
     */
    public function products()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $productQuery = Product::query()
                        ->withoutGlobalScope('store');

        $products = QueryBuilder::for($productQuery)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes([
                'images',
                'variants',
                'categories',
                'attributeValues',
            ])
            ->allowedFilters([
                'categories.id',
                'attributeValues.id',
                'attributeValues.attribute.id',
                'variants.id',
                'variants.sku',
                'variants.categories.id',
                'variants.attributeValues.id',
                AllowedFilter::scope('low_stock', 'lowStock'),
                AllowedFilter::scope('out_of_stock', 'outOfStock'),

            ]);

        $products->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $products = $products->paginate($perPage)
                ->appends(request()->query());

        } else {
            $products = $products->get();
        }

        return ProductResource::collection($products)->additional([
            'status' => 'success',
            'message' => 'Products retrieved successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function showProduct(Product $product): ProductResource
    {
        $product->loadFromRequest();

        return (new ProductResource($product))->additional([
            'message' => 'Product retrieved successfully',
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/sync/ProductVariants
     */
    public function productVariants()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $productVariantQ = ProductVariant::query()
            ->withoutGlobalScope('store');

        $productVariants = QueryBuilder::for($productVariantQ)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes([
                'images',
                'product.images',
                'product.categories',
                'product.attributeValues.attribute',
                'attributeValues.attribute',
            ])
            ->allowedFilters([
                'categories.id',
                'attributeValues.id',
                'attributeValues.attribute.id',

                'attributeValues.id',
                'attributeValues.attribute.id',
                'product.attributeValues.id',
                'product.attributeValues.attribute.id',
                AllowedFilter::scope('low_stock', 'lowStock'),
                AllowedFilter::scope('out_of_stock', 'outOfStock'),
            ]);

        $productVariants->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (!in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = !is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $productVariants = $productVariants->paginate($perPage)
                ->appends(request()->query());

        } else {
            $productVariants = $productVariants->get();
        }

        return ProductVariantResource::collection($productVariants)->additional([
            'status' => 'success',
            'message' => 'Product variants retrieved successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function showProductVariant(ProductVariant $productVariant)
    {
        $productVariant->loadFromRequest();

        return (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant retrieved successfully',
        ]);
    }
}
