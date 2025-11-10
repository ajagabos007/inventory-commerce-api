<?php

namespace App\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class ProductPopularSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        // Subquery that computes total_sold per product
        $salesSubquery = \DB::table('product_variants')
            ->selectRaw('product_variants.product_id, COALESCE(SUM(sale_inventories.quantity), 0) AS total_sold')
            ->join('inventories', 'inventories.product_variant_id', '=', 'product_variants.id')
            ->join('sale_inventories', 'sale_inventories.inventory_id', '=', 'inventories.id')
            ->join('sales', 'sales.id', '=', 'sale_inventories.sale_id')
            ->groupBy('product_variants.product_id');

        // Join subquery to products and sort by total_sold
        $query->leftJoinSub($salesSubquery, 'product_popular_sort', function ($join) {
            $join->on('products.id', '=', 'product_popular_sort.product_id');
        })
            ->select('products.*')
            ->addSelect(\DB::raw('COALESCE(product_popular_sort.total_sold, 0) AS total_sold'))
            ->orderBy('total_sold', $descending ? 'desc' : 'asc');

        return $query;
    }
}
