<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/invetory/products
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $inventoryQ = Inventory::when(! auth()->user()?->is_admin, function ($query) {
            return $query->belongingToCurrentStaff();
        });

        $products = QueryBuilder::for($inventoryQ)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'store_id',
                'product_id',
                'item.category_id',
                'item.type_id',
                'item.colour_id',
                'item.store_id',
                'item.material',
                AllowedFilter::scope('low_stock', 'lowStock'),
                AllowedFilter::scope('out_of_stock', 'outOfStock'),
            ])
            ->allowedIncludes([
                'item',
                'store',
                'item.type',
                'item.colour',
                'item.material',
                'item.category',
            ])
            ->with(['item', 'store']);

        if (request()->has('q')) {
            $searchTerm = '%'.request()->q.'%';
            $products->where(function ($query) use ($searchTerm) {
                $model = $query->getModel();
                $table = $model->getTable();

                $cacheKey = "{$table}_column_listing";
                $columns = Cache::rememberForever($cacheKey, function () use ($table) {
                    return Schema::getColumnListing($table);
                });

                foreach ($columns as $index => $column) {
                    $command = $index == 0 ? 'where' : 'orWhere';
                    $query->{$command}($column, 'like', $searchTerm);
                }
            })
                ->orWhereHas('item', function ($query) use ($searchTerm) {
                    $model = $query->getModel();
                    $table = $model->getTable();
                    $searchTerm = '%'.request()->q.'%';

                    $cacheKey = "{$table}_column_listing";
                    $columns = Cache::rememberForever($cacheKey, function () use ($table) {
                        return Schema::getColumnListing($table);
                    });

                    foreach ($columns as $index => $column) {
                        $command = $index == 0 ? 'where' : 'orWhere';
                        $query->{$command}($column, 'like', $searchTerm);
                    }
                });
        }

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0'], true)) {
            /**
             * Ensure per_page is integer and >= 1
             */
            if (! is_numeric($perPage)) {
                $perPage = 15;
            } else {
                $perPage = intval($perPage);
                $perPage = $perPage >= 1 ? $perPage : 15;
            }

            $products = $products->paginate($perPage)
                ->appends(request()->query());

        } else {
            $products = $products->get();
        }

        $inventory_products_collection = InventoryResource::collection($products)->additional([
            'status' => 'success',
            'message' => 'Inventory Products retrieved successfully',
        ]);

        return $inventory_products_collection;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request) {}

    /**
     * Display the specified resource.
     */
    public function show(Inventory $item)
    {

        $item->loadFromRequest();

        $inventory_resource = (new InventoryResource($item))->additional([
            'message' => 'Product retrieved successfully',
        ]);

        return $inventory_resource;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Inventory $inventory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryRequest $request, Inventory $inventory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Inventory $inventory)
    {
        //
    }
}
