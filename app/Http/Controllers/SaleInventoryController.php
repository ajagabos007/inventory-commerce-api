<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleInventoryRequest;
use App\Http\Requests\UpdateSaleInventoryRequest;
use App\Http\Resources\SaleInventoryResource;
use App\Models\DailyGoldPrice;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\SaleInventory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SaleInventoryController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(SaleInventory::class, 'sale_inventory');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/sales
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $per_page = request()->has('per_page') ? request()->per_page : 15;

        $sale_inventorys = QueryBuilder::for(SaleInventory::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'quantity',
                'price',
                'total_price',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'code',
                AllowedFilter::exact('is_active'),
            ])
            ->allowedIncludes([
                'sale',
                'inventory',
                'inventory.item',
                'inventory.item.category',
            ]);

        if (request()->has('q')) {
            $searchTerm = '%'.request()->q.'%';
            $sale_inventorys->where(function ($query) use ($searchTerm) {
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
                $commandHas = $command.'Has';
                $query->{$commandHas}('inventory.item', function ($query) use ($searchTerm) {
                    $query->where('sku', $searchTerm);
                });
            })
                ->orWhereHas('inventory.item', function ($query) use ($searchTerm) {
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
                });
        }

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0'], true)) {
            /**
             * Ensure per_page is integer and >= 1
             */
            if (! is_numeric($per_page)) {
                $per_page = 15;
            } else {
                $per_page = intval($per_page);
                $per_page = $per_page >= 1 ? $per_page : 15;
            }

            $sale_inventorys = $sale_inventorys->paginate($per_page)
                ->appends(request()->query());

        } else {
            $sale_inventorys = $sale_inventorys->get();
        }

        $sale_inventorys_collection = SaleInventoryResource::collection($sale_inventorys)->additional([
            'status' => 'success',
            'message' => 'SaleInventorys retrieved successfully',
        ]);

        return $sale_inventorys_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSaleInventoryRequest $request)
    {
        DB::beginTransaction();

        // Get the gold rate for today
        $daily_gold_price = DailyGoldPrice::period('today')->first();

        if (! $daily_gold_price) {
            throw new \Exception('No daily gold price found for today.');
        }

        try {

            $subtotal_price = 0;
            $sale_inventory_item_data = [];
            $validated = $request->validated();

            foreach ($validated['products'] as $inventory) {

                $invent = Inventory::find($inventory['id']);
                if (! $invent) {
                    continue;
                }

                $price_per_gram = $daily_gold_price->price_per_gram;

                if (in_array('price_per_gram', $inventory) && $inventory['price_per_gram'] > 0) {
                    $price_per_gram = $inventory['price_per_gram'];
                }
                $total_price = $price_per_gram * $inventory['quantity'];

                if ($invent->item->weight > 0) {
                    $total_price *= ($invent->item->weight);
                }

                $subtotal_price += $total_price;
                $invent->decrement('quantity', $inventory['quantity']);

                $sale_inventory_item_data[] = [
                    'inventory_id' => $inventory['id'],
                    'quantity' => $inventory['quantity'],
                    'weight' => $invent->item->weight,
                    'price_per_gram' => $price_per_gram,
                    'total_price' => $total_price,
                    'daily_gold_price_id' => $daily_gold_price->id,
                ];

                if ($invent = Inventory::find($inventory['id'])) {
                    $invent->decrement('quantity', $inventory['quantity']);
                }

            }

            $subtotal_price = ($reqeust->tax ?? 0) + $subtotal_price;
            $total_price = $subtotal_price;

            $discount = Discount::find($request->discount_id);
            if ($discount) {
                $total_price = $subtotal_price - ($subtotal_price * ($discount->percentage / 100));
            }

            // Calculate tax and total
            $sale_inventory = SaleInventory::create([
                'payment_method' => $request->payment_method,
                'customer_id' => $request->customer_id,
                'customer_name' => $request->customer_name,
                'customer_phone_number' => $request->customer_phone_number,
                'customer_email' => $request->customer_email,
                'discount_id' => $request->discount_id,
                'tax' => $request->tax ?? 0,
                'subtotal_price' => $subtotal_price,
                'total_price' => $total_price,
            ]);

            $metadata['discount'] = $discount;
            $sale_inventory->metadata = $metadata;
            $sale_inventory->save();

            $sale_inventory->inventories()->createMany($sale_inventory_item_data);

            DB::commit();

            $sale_inventory->load('inventories');
            $sale_inventory_resource = (new SaleInventoryResource($sale_inventory))->additional([
                'message' => 'Sale inventory created successfully',
            ]);

            return $sale_inventory_resource;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->json([
                'message' => 'Failed to create sale.',
                'errors' => ['create_sale' => $e->getMessage()],
            ], 500);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(SaleInventory $sale_inventory)
    {
        $sale_inventory->applyRequestIncludesAndAppends();

        $sale_inventory_resource = (new SaleInventoryResource($sale_inventory))->additional([
            'message' => 'Sale inventory retrieved successfully',
        ]);

        return $sale_inventory_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSaleInventoryRequest $request, SaleInventory $sale_inventory)
    {
        $validated = $request->validated();
        $sale_inventory->update($validated);

        $sale_inventory->applyRequestIncludesAndAppends();

        $sale_inventory_resource = (new SaleInventoryResource($sale_inventory))->additional([
            'message' => 'Sale inventory updated successfully',
        ]);

        return $sale_inventory_resource;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SaleInventory $sale_inventory)
    {
        $sale_inventory->delete();

        $sale_inventory_resource = (new SaleInventoryResource(null))->additional([
            'message' => 'Sale inventory deleted successfully',
        ]);

        return $sale_inventory_resource;
    }
}
