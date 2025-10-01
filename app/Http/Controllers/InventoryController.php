<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/invetories
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $inventoryQ = Inventory::query();

        $inventories = QueryBuilder::for($inventoryQ)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'store_id',
                'product_variant_id',
                'productVariant.product_id',
                AllowedFilter::scope('low_stock', 'lowStock'),
                AllowedFilter::scope('out_of_stock', 'outOfStock'),
            ])
            ->allowedIncludes([
                'productVariant.product.attributeValues',
                'productVariant.product.images',
                'productVariant.attributeValues',
                'productVariant.images',
                'store',
            ]);

        $inventories->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $inventories = $inventories->paginate($perPage)
                ->appends(request()->query());

        } else {
            $inventories = $inventories->get();
        }

        return InventoryResource::collection($inventories)->additional([
            'status' => 'success',
            'message' => 'Inventories retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request) {}

    /**
     * Display the specified resource.
     */
    public function show(Inventory $inventory)
    {
        $inventory->loadFromRequest();

        return (new InventoryResource($inventory))->additional([
            'message' => 'Inventory retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryRequest $request, Inventory $inventory)
    {
        $validated = $request->validated();

        if(array_key_exists('serial_number',$validated) && !$inventory->productVariant->is_serialized) {
           $validated['serial_number'] = null;
        }

        $inventory->update($validated);

        return (new InventoryResource($inventory))->additional([
            'message' => 'Inventory updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Inventory $inventory)
    {
        //
    }
}
