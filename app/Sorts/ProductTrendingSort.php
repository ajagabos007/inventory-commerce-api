<?php

namespace App\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class ProductTrendingSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $days = request()->integer('trending_days', 30);
        $startDate = now()->subDays($days)->startOfDay();

        // Define subquery that computes total sold per product within date range
        $salesSubquery = \DB::table('product_variants')
            ->selectRaw('product_variants.product_id, COALESCE(SUM(sale_inventories.quantity), 0) AS total_sold')
            ->join('inventories', 'inventories.product_variant_id', '=', 'product_variants.id')
            ->join('sale_inventories', 'sale_inventories.inventory_id', '=', 'inventories.id')
            ->join('sales', 'sales.id', '=', 'sale_inventories.sale_id')
            ->where('sales.created_at', '>=', $startDate)
            ->groupBy('product_variants.product_id');

        $query->leftJoinSub($salesSubquery, 'product_trending_sort', function ($join) {
            $join->on('products.id', '=', 'product_trending_sort.product_id');
        });

        // Select products and aggregated sales
        $query->select('products.*')
            ->addSelect(\DB::raw('COALESCE(product_trending_sort.total_sold, 0) AS total_sold'))
            ->having('total_sold', '>', 0)
            ->orderBy('total_sold', $descending ? 'desc' : 'asc');

        return $query;

    }
}
