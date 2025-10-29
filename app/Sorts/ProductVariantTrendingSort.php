<?php
namespace App\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class ProductVariantTrendingSort implements Sort
{

    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $days = request()->input('trending_days', 30);
        $startDate = now()->subDays($days)->toDateTimeString();

        $query->select('product_variants.*')
            ->selectRaw('COALESCE(SUM(sale_inventories.quantity), 0) as total_sold')
            ->leftJoin('inventories', 'inventories.product_variant_id', '=', 'product_variants.id')
            ->leftJoin('sale_inventories', 'sale_inventories.inventory_id', '=', 'inventories.id')
            ->leftJoin('sales', function ($join) use ($startDate) {
                $join->on('sales.id', '=', 'sale_inventories.sale_id')
                    ->where('sales.created_at', '>=', $startDate);
            })
            ->groupBy('product_variants.id')
            ->having('total_sold', '>', 0);

        return $descending
            ? $query->orderByDesc('total_sold')
            : $query->orderBy('total_sold');
    }
}
