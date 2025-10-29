<?php
namespace App\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class ProductVariantPopularSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $query->select('product_variants.*')
            ->selectRaw('COALESCE(SUM(sale_inventories.quantity), 0) as total_sold')
            ->leftJoin('inventories', 'inventories.product_variant_id', '=', 'product_variants.id')
            ->leftJoin('sale_inventories', 'sale_inventories.inventory_id', '=', 'inventories.id')
            ->leftJoin('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_inventories.sale_id')
                    ->where('sales.status', '=', 'completed');
            })
            ->groupBy('product_variants.id');

        return $descending
            ? $query->orderByDesc('total_sold')
            : $query->orderBy('total_sold');
    }
}
