<?php
namespace App\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class ProductTrendingSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $days = request()->input('trending_days', 30);
        $startDate = now()->subDays($days)->toDateTimeString();

        $query->select('products.*')
            ->selectRaw('COALESCE(SUM(sale_inventories.quantity), 0) as total_sold')
            ->leftJoin('product_variants', 'product_variants.product_id', '=', 'products.id')
            ->leftJoin('inventories', 'inventories.product_variant_id', '=', 'product_variants.id')
            ->leftJoin('sale_inventories', 'sale_inventories.inventory_id', '=', 'inventories.id')
            ->leftJoin('sales', function ($join) use ($startDate) {
                $join->on('sales.id', '=', 'sale_inventories.sale_id')
                    ->where('sales.created_at', '>=', $startDate);
            })
            ->groupBy('products.id')
            ->having('total_sold', '>', 0);

        return $descending
            ? $query->orderByDesc('total_sold')
            : $query->orderBy('total_sold');
    }
}
