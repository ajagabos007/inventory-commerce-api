<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleAddInventoryRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Sale;
use App\Models\SaleInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $salesQ = Sale::query();

        $sales = QueryBuilder::for($salesQ)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'payment_method',
                'channel',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'discount.id',
                'discount.code',
                'buyerable_id',
                'cashier_staff_id',
                'payment_method',
                'channel',
            ])
            ->allowedIncludes([
                'saleInventories.inventory.productVariant.product',
                'buyerable',
                'cashier.user',
                'discount',
            ]);

        $sales->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $sales = $sales->paginate($perPage)
                ->appends(request()->query());

        } else {
            $sales = $sales->get();
        }

        return SaleResource::collection($sales)->additional([
            'status' => 'success',
            'message' => 'Sales retrieved successfully',
        ]);
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
                ->with('productVariant.product')
                ->get();

            foreach ($validated['sale_inventories'] as $inventory) {

                $invent = $inventories->where('id', $inventory['inventory_id'])
                    ->first();

                if (! $invent) {
                    continue;
                }

                $price = $invent->productVariant->price;

                $total_price = $price * $inventory['quantity'];

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
                    'price' => $price,
                    'total_price' => $total_price,
                ];

            }

            $subtotal_price = ($reqeust->tax ?? 0) + $subtotal_price;
            $total_price = $subtotal_price;

            $discount = Discount::where('code', $validated['discount_code'] ?? null)->first();

            if (! blank($discount)) {
                $total_price = $subtotal_price - ($subtotal_price * (1 / 100 * $discount->percentage ?? 0));
            }

            $sale = Sale::create([
                'payment_method' => $request->payment_method,
                'buyerable_id' => $request->customer_id,
                'buyerable_type' => Customer::class,
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

            $sale->refresh();

            $sale->load([
                'buyerable',
                'saleInventories.inventory.productVariant',
                'discount',
                'cashier.user',
            ]);

            return (new SaleResource($sale))->additional([
                'message' => 'Sale created successfully',
            ]);

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

                /** create new sale iventoris if null */
                if (blank($sale_invent)) {

                    $invent = Inventory::find($sale_inventory['inventory_id'] ?? null)
                        ->first();

                    if (! $invent) {
                        continue;
                    }
                    $price = $invent->productVariant->price;

                    $total_price = $price * $sale_inventory['quantity'];

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
                        'price' => $price,
                        'total_price' => $total_price,
                    ];

                    $sale->saleInventories()->createMany($sale_item_data);

                    continue;
                }

                $old_quantity = $sale_invent->quantity;
                $sale_invent->update($sale_inventory);

                $sale_invent->total_price = $sale_invent->price * $sale_invent->quantity;
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

            return (new SaleResource($sale))->additional([
                'message' => 'Sale updated successfully',
            ]);

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
            $sale_inventory = $sale->saleInventories()
                ->where('inventory_id', $validated['inventory_id'])
                ->first();

            if ($sale_inventory) {
                $sale->load('saleInventories.inventory.productVariant.product');

                return (new SaleResource($sale))->additional([
                    'message' => 'Sale inventory added successfully',
                ]);
            }

            $sale_inventory = new SaleInventory;
            $sale_inventory->sale_id = $sale->id;
            $invent = Inventory::find($validated['inventory_id']);
            $invent->quantity = $validated['quantity'];

            $sale_inventory->inventory_id = $validated['inventory_id'];
            $sale_inventory->quantity = $validated['quantity'];
            $sale_inventory->price = $invent->productVariant->price;
            $sale_inventory->total_price = $sale_inventory->price * $validated['quantity'];

            if ($sale_inventory->inventory->quantity >= $sale_inventory->quantity) {
                $sale_inventory->inventory->decrement('quantity', $sale_inventory->quantity);
            } else {
                $sale_inventory->inventory->update([
                    'quantity' => 0,
                ]);
            }

            $sale_inventory->save();
            $sale = $sale->updatePricing();
            $sale->load('saleInventories.inventory.productVariant.product');

            DB::commit();

            return (new SaleResource($sale))->additional([
                'message' => 'Sale inventory added successfully',
            ]);

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
            $sale->load('saleInventories.inventory.productVariant.product');

            DB::commit();

            return (new SaleResource($sale))->additional([
                'message' => 'Sale inventory deleted successfully',
            ]);

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

        return (new SaleResource(null))->additional([
            'message' => 'Sale deleted successfully',
        ]);
    }
}
