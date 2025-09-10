<?php

namespace App\Http\Controllers;

use App\Enums\Material;
use App\Http\Requests\SaleAddInventoryRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\DailyGoldPrice;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Sale;
use App\Models\SaleInventory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\QueryBuilder;

class SaleController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Sale::class, 'sale');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/sales
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $salesQ = Sale::when(! auth()->user()?->is_admin, function ($query) {
            $query->visibleToUser();
        });

        $sales = QueryBuilder::for($salesQ)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'customer_name',
                'customer_email',
                'customer_phone_number',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'discount.id',
                'discount.code',
                'customer_name',
                'customer_email',
                'customer_phone_number',
            ])
            ->allowedIncludes([
                'saleInventories',
                'saleInventories.inventory.item',
                'saleInventories.inventory.item.category',
                'saleInventories.daily_gold_price',
                'customer',
                'cashier.user',
                'discount',
            ]);

        if (request()->has('q')) {
            $searchTerm = '%'.request()->q.'%';
            $sales->where(function ($query) use ($searchTerm) {
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
                ->orWhereHas('saleInventories.inventory.item', function ($query) use ($searchTerm) {
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
            if (! is_numeric($perPage)) {
                $perPage = 15;
            } else {
                $perPage = intval($perPage);
                $perPage = $perPage >= 1 ? $perPage : 15;
            }

            $sales = $sales->paginate($perPage)
                ->appends(request()->query());

        } else {
            $sales = $sales->get();
        }

        $sales_collection = SaleResource::collection($sales)->additional([
            'status' => 'success',
            'message' => 'Sales retrieved successfully',
        ]);

        return $sales_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSaleRequest $request)
    {
        DB::beginTransaction();

        try {

            $subtotal_price = 0;
            $sale_item_data = [];
            $validated = $request->validated();

            $sale_inventories = data_get(request()->all(), 'sale_inventories');

            $inventories = Inventory::whereIn('id', array_column($sale_inventories, 'inventory_id'))
                ->with('item.category')
                ->get();

            $daily_gold_prices = DailyGoldPrice::period('today')
                ->where(function ($query) use ($inventories) {
                    $query->whereIn('category_id', $inventories->pluck('item.category_id')->toArray())
                        ->orWhereNull('category_id');
                })
                ->get();

            foreach ($validated['sale_inventories'] as $inventory) {

                $invent = $inventories->where('id', $inventory['inventory_id'])
                    ->first();
                if (! $invent) {
                    continue;
                }

                if ($invent->item->material == Material::GOLD->value) {

                    $daily_gold_price = $daily_gold_prices
                        ->when(! empty($invent->item->category_id), function ($query) use ($invent) {
                            $query->where('category_id', $invent->item->category_id);
                        }, function ($query) {
                            $query->whereNull('category_id');
                        })
                        ->first();
                    if (in_array('price_per_gram', $inventory) && $inventory['price_per_gram'] > 0) {
                        $price_per_gram = $inventory['price_per_gram'];
                    } else {
                        $price_per_gram = $daily_gold_price->price_per_gram;
                    }
                } else {
                    $price_per_gram = $invent->item->price;
                }

                $total_price = $price_per_gram * $inventory['quantity'];

                if ($invent->item->weight > 0) {
                    $total_price *= ($invent->item->weight);
                }

                $subtotal_price += $total_price;
                if ($invent->quantity > $inventory['quantity']) {
                    $invent->decrement('quantity', $inventory['quantity']);
                } else {
                    $invent->update([
                        'quantity' => 0,
                    ]);
                }

                $sale_item_data[] = [
                    'inventory_id' => $inventory['inventory_id'],
                    'quantity' => $inventory['quantity'],
                    'weight' => $invent->item->weight,
                    'price_per_gram' => $price_per_gram,
                    'total_price' => $total_price,
                    'daily_gold_price_id' => $daily_gold_price->id,
                ];

            }

            $subtotal_price = ($reqeust->tax ?? 0) + $subtotal_price;
            $total_price = $subtotal_price;

            $discount = Discount::where('code', $validated['discount_code'] ?? null)->first();
            if ($discount) {
                $total_price = $subtotal_price - ($subtotal_price * (1 / 100 * $discount->percentage ?? 0));
            }

            // Calculate tax and total
            $sale = Sale::create([
                'payment_method' => $request->payment_method,
                'customer_id' => $request->customer_id,
                'customer_name' => $request->customer_name,
                'customer_phone_number' => $request->customer_phone_number,
                'customer_email' => $request->customer_email,
                'discount_id' => is_null($discount) ? null : $discount->id ?? null,
                'tax' => $request->tax ?? 0,
                'subtotal_price' => $subtotal_price,
                'total_price' => $total_price,
            ]);

            $metadata['discount'] = $discount;
            $sale->metadata = $metadata;
            $sale->save();

            $sale->saleInventories()->createMany($sale_item_data);

            DB::commit();

            $sale->load('saleInventories.inventory.item');
            $sale_resource = (new SaleResource($sale))->additional([
                'message' => 'Sale created successfully',
            ]);

            return $sale_resource;

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
    public function show(Sale $sale)
    {
        $sale->loadFromRequest();

        $sale->load('saleInventories.inventory.item.category');

        $sale_resource = (new SaleResource($sale))->additional([
            'message' => 'Sale retrieved successfully',
        ]);

        return $sale_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSaleRequest $request, Sale $sale)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            $sale->update($validated);

            $discount = Discount::where('code', $validated['discount_code'] ?? null)->first();
            if ($discount) {
                $sale->discount_id = $discount->id;
                $sale->save();
            }

            foreach ($validated['sale_inventories'] ?? [] as $sale_inventory) {

                $sale_invent = SaleInventory::find($sale_inventory['id'] ?? null);
                if (! $sale_invent) {

                    $invent = Inventory::find($sale_inventory['inventory_id'] ?? null)
                        ->with('item.category')
                        ->first();
                    if (! $invent) {
                        continue;
                    }

                    if ($invent->item->material == Material::GOLD->value) {
                        // Get the gold rate for today
                        $daily_gold_price = DailyGoldPrice::period('today')
                            ->when(! empty($invent->item->category_id), function ($query) use ($invent) {
                                $query->where('category_id', $invent->item->category_id);
                            }, function ($query) {
                                $query->whereNull('category_id');
                            })
                            ->first();

                        if (! $daily_gold_price) {
                            $error = empty($invent->item->category_id)
                            ? "No daily gold price found for today. Please set today\'s gold price in the system."
                            : "No daily gold price found for today. Please set today's price for '{$invent->item->category->name}' category.";

                            throw new \Exception($error);
                        }

                        if (in_array('price_per_gram', $sale_inventory) && $sale_inventory['price_per_gram'] > 0) {
                            $price_per_gram = $sale_inventory['price_per_gram'] ?? 1;
                        } else {
                            $price_per_gram = $daily_gold_price->price_per_gram;
                        }

                    } else {
                        $price_per_gram = $invent->item->price;
                    }

                    $total_price = $price_per_gram * $sale_inventory['quantity'];

                    if ($invent->item->weight > 0) {
                        $total_price *= ($invent->item->weight);
                    }

                    if ($invent->quantity > $sale_inventory['quantity']) {
                        $invent->decrement('quantity', $sale_inventory['quantity']);
                    } else {
                        $invent->update([
                            'quantity' => 0,
                        ]);
                    }

                    $sale_item_data[] = [
                        'inventory_id' => $sale_inventory['inventory_id'],
                        'quantity' => $sale_inventory['quantity'],
                        'weight' => $invent->item->weight,
                        'price_per_gram' => $price_per_gram,
                        'total_price' => $total_price,
                        'daily_gold_price_id' => $daily_gold_price->id,
                    ];

                    $sale->saleInventories()->createMany($sale_item_data);

                    continue;
                }

                $old_quantity = $sale_invent->quantity;
                $sale_invent->update($sale_inventory);

                $sale_invent->total_price = $sale_invent->price_per_gram * $sale_invent->quantity;
                $sale_invent->save();

                if ($old_quantity != $sale_invent->quantity && $sale_invent->inventory) {
                    if ($sale_invent->quantity > $old_quantity) {
                        $diff_quantity = $sale_invent->quantity - $old_quantity;
                        $sale_invent->inventory->increment('quantity', $diff_quantity);
                    } else {
                        $diff_quantity = $old_quantity - $sale_invent->quantity;
                        $sale_invent->inventory->decrement('quantity', $diff_quantity);
                    }
                }

            }

            $subtotal_price = $sale->saleInventories()->sum('total_price');

            $subtotal_price = ($sale->tax ?? 0) + $subtotal_price;
            $total_price = $subtotal_price;

            $metadata = $sale->metadata ?? [];

            if ($sale->wasChanged('discount_id')) {
                $discount = Discount::find($sale->discount_id);
                if ($discount) {
                    $total_price = $subtotal_price - ($subtotal_price * ($discount->percentage / 100));
                }
                $metadata['discount'] = $discount;
            } else {
                $discount = $metadata['discount'] ?? null;
                if ($discount) {
                    $total_price = $subtotal_price - ($subtotal_price * (1 / 100 * $discount['percentage'] ?? 0));
                }
            }

            // Update sale details
            $sale->subtotal_price = $subtotal_price;
            $sale->total_price = $total_price;
            $sale->metadata = $metadata;
            $sale->save();

            DB::commit();

            $sale->load('saleInventories');
            $sale_resource = (new SaleResource($sale))->additional([
                'message' => 'Sale updated successfully',
            ]);

            return $sale_resource;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->json([
                'message' => 'Failed to update sale.',
                'errors' => ['update_sale' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function storeSaleInventory(SaleAddInventoryRequest $request, Sale $sale)
    {

        DB::beginTransaction();

        try {

            $validated = $request->validated();
            $sale_inventory = $sale->saleInventories()->find($validated['inventory_id']);

            if (! $sale_inventory) {
                $sale_inventory = new SaleInventory;
                $sale_inventory->sale_id = $sale->id;
            }

            $invent = Inventory::find('id', $validated['inventory_id']);

            if ($invent->item->material == Material::GOLD->value) {

                $daily_gold_price = DailyGoldPrice::period('today')
                    ->when(! empty($invent->item->category_id), function ($query) use ($invent) {
                        $query->where('category_id', $invent->item->category_id);
                    }, function ($query) {
                        $query->whereNull('category_id');
                    })
                    ->first();

                if (! $daily_gold_price) {
                    $error = empty($invent->item->category_id)
                    ? "No daily gold price found for today. Please set today\'s gold price in the system."
                    : "No daily gold price found for today. Please set today's price for '{$invent->item->category->name}' category.";

                    throw new \Exception($error);
                }

                if (in_array('price_per_gram', $validated) && $validated['price_per_gram'] > 0) {
                    $price_per_gram = $validated['price_per_gram'];
                } else {
                    $price_per_gram = $daily_gold_price->price_per_gram;
                }
            } else {
                $price_per_gram = $invent->item->price;
            }

            $sale_inventory->inventory_id = $validated['inventory_id'];
            $sale_inventory->quantity = $validated['quantity'];
            $sale_inventory->price_per_gram = $price_per_gram;
            $sale_inventory->total_price = $price_per_gram * $validated['quantity'];

            if ($sale_inventory->inventory->quantity >= $sale_inventory->quantity) {
                $sale_inventory->inventory->decrement('quantity', $sale_inventory->quantity);
            } else {
                $sale_inventory->inventory->update([
                    'quantity' => 0,
                ]);
            }

            $sale_inventory->save();
            $sale = $sale->updatePricing();
            $sale->load('saleInventories.inventory.item');

            DB::commit();

            $sale_resource = (new SaleResource($sale))->additional([
                'message' => 'Sale inventory deleted successfully',
            ]);

            return $sale_resource;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->json([
                'message' => 'Failed to add sale inventory.',
                'errors' => ['add_sale_inventory' => $e->getMessage()],
            ], 500);
        }

    }

    /**
     * Relation the specified resource in storage.
     */
    public function destroySaleInventory(Sale $sale, SaleInventory $sale_inventory)
    {
        DB::beginTransaction();

        try {

            if (! $sale->saleInventories()->where('id', $sale_inventory->id)->exists()) {
                $sale_resource = (new SaleResource($sale))->additional([
                    'message' => 'Sale inventory deleted successfully',
                ]);

                return $sale_resource;
            }

            $sale_inventory->inventory->increment('quantity', $sale_inventory->quantity);
            $sale->saleInventories()->where('id', $sale_inventory->id)->delete();

            if ($sale->saleInventories()->count() > 0) {
                $sale->subtotal_price = $sale->saleInventories()->sum('total_price');
                $sale->total_price += ($sale->tax ?? 0);

                $metadata = $sale->metadata ?? [];

                if (array_key_exists('discount', $metadata) && $metadata['discount'] instanceof Discount) {
                    $discount = $metadata['discount'];
                    $sale->total_price += $sale->subtotal_price - ($sale->subtotal_price * ($discount['percentage'] / 100));
                }
            } else {
                $sale->subtotal_price = 0;
                $sale->total_price = 0;
            }

            $sale->save();
            $sale->load('saleInventories.inventory.item');

            DB::commit();

            $sale_resource = (new SaleResource($sale))->additional([
                'message' => 'Sale inventory deleted successfully',
            ]);

            return $sale_resource;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->json([
                'message' => 'Failed to delete sale inventory.',
                'errors' => ['delete_sale_inventory' => $e->getMessage()],
            ], 500);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sale $sale)
    {
        $sale->delete();

        $sale_resource = (new SaleResource(null))->additional([
            'message' => 'Sale deleted successfully',
        ]);

        return $sale_resource;
    }
}
