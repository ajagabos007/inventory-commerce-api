<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Http\Requests\StoreStockTransferRequest;
use App\Http\Requests\UpdateStockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\Inventory;
use App\Models\StockTransfer;
use App\Models\StockTransferInventory;
use App\Models\User;
use App\Notifications\StockTransferDispatchedNotification;
use App\Notifications\StockTransferReceivedNotification;
use App\Notifications\StockTransferRejectedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockTransferController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(StockTransfer::class, 'stock_transfer');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/staffs
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $stock_transfersQ = StockTransfer::query();

        if ($stock_transfersQ->clone()->where('status')) {
            $stock_transfers = QueryBuilder::for($stock_transfersQ)
                ->defaultSort('-created_at')
                ->allowedSorts(
                    'driver_name',
                    'driver_phone_number',
                    'status',
                    'created_at',
                    'updated_at',
                )
                ->allowedFilters([
                    'sender_id',
                    'receiver_id',
                    'from_store_id',
                    'to_store_id',
                    'status',
                    AllowedFilter::scope('in_coming'),
                    AllowedFilter::scope('out_going'),
                ])
                ->allowedIncludes([
                    'receiver',
                    'sender',
                    'fromStore',
                    'toStore',
                    'inventories',
                    'inventories.item',
                    'inventories.item.store',
                    'inventories.item.category',
                    'inventories.item.type',
                    'inventories.item.image',
                    'stockTransferInventories',
                    'stockTransferInventories.inventory',
                    'stockTransferInventories.inventory.item',
                    'stockTransferInventories.inventory.item.image',
                    'stockTransferInventories.inventory.item.category',
                    'stockTransferInventories.inventory.item.type',
                    'stockTransferInventories.inventory.item.colour',
                ]);
        }

        if (request()->has('q')) {
            $stock_transfers->where(function ($query) {
                $table_cols_key = $query->getModel()->getTable().'_column_listing';

                if (Cache::has($table_cols_key)) {
                    $cols = Cache::get($table_cols_key);
                } else {
                    $cols = Schema::getColumnListing($query->getModel()->getTable());
                    Cache::put($table_cols_key, $cols);
                }

                foreach ($cols as $index => $col) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->$method($col, 'LIKE', '%'.request()->q.'%');
                }
            })
                ->orWhereHas('sender', function ($query) {
                    $table_cols_key = $query->getModel()->getTable().'_column_listing';

                    if (Cache::has($table_cols_key)) {
                        $cols = Cache::get($table_cols_key);
                    } else {
                        $cols = Schema::getColumnListing($query->getModel()->getTable());
                        Cache::put($table_cols_key, $cols);
                    }
                    foreach ($cols as $index => $col) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->$method($col, 'LIKE', '%'.request()->q.'%');
                    }
                })
                ->orWhereHas('receiver', function ($query) {
                    $table_cols_key = $query->getModel()->getTable().'_column_listing';

                    if (Cache::has($table_cols_key)) {
                        $cols = Cache::get($table_cols_key);
                    } else {
                        $cols = Schema::getColumnListing($query->getModel()->getTable());
                        Cache::put($table_cols_key, $cols);
                    }

                    foreach ($cols as $index => $col) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->$method($col, 'LIKE', '%'.request()->q.'%');
                    }
                })
                ->orWhereHas('fromStore', function ($query) {
                    $table_cols_key = $query->getModel()->getTable().'_column_listing';

                    if (Cache::has($table_cols_key)) {
                        $cols = Cache::get($table_cols_key);
                    } else {
                        $cols = Schema::getColumnListing($query->getModel()->getTable());
                        Cache::put($table_cols_key, $cols);
                    }

                    foreach ($cols as $index => $col) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->$method($col, 'LIKE', '%'.request()->q.'%');
                    }
                })
                ->orWhereHas('toStore', function ($query) {
                    $table_cols_key = $query->getModel()->getTable().'_column_listing';

                    if (Cache::has($table_cols_key)) {
                        $cols = Cache::get($table_cols_key);
                    } else {
                        $cols = Schema::getColumnListing($query->getModel()->getTable());
                        Cache::put($table_cols_key, $cols);
                    }

                    foreach ($cols as $index => $col) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->$method($col, 'LIKE', '%'.request()->q.'%');
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

            $stock_transfers = $stock_transfers->paginate($perPage)
                ->appends(request()->query());

        } else {
            $stock_transfers = $stock_transfers->get();
        }

        $stock_transfers = $stock_transfers->filter(function ($stock_transfer) {
            if ($stock_transfer->status === Status::NEW->value) {
                return $stock_transfer->from_store_id == auth()->user()?->staff?->store_id ||
                       $stock_transfer->sender_id == auth()->id();
            }

            return true;
        });

        $stock_transfers_collection = StockTransferResource::collection($stock_transfers)->additional([
            'status' => 'success',
            'message' => 'Stock transfers retrieved successfully',
        ]);

        return $stock_transfers_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStockTransferRequest $request)
    {
        $validated = $request->validated();

        try {

            DB::beginTransaction();

            $stock_transfer = StockTransfer::create($validated);

            $stock_transfer_inventories = data_get($validated, 'stock_transfer_inventories', []);
            $entries = collect([]);

            foreach ($stock_transfer_inventories as $stock_inventory) {
                $entries->push([
                    'inventory_id' => $stock_inventory['inventory_id'],
                    'quantity' => $stock_inventory['quantity'],
                    'stock_transfer_id' => $stock_transfer->id,
                ]);
            }

            if ($entries->isNotEmpty()) {
                StockTransferInventory::upsert(
                    $entries->toArray(),
                    uniqueBy: ['stock_transfer_id', 'inventory_id'],
                    update: ['quantity']
                );
            }

            $stock_transfer->load([
                'receiver',
                'sender',
                'fromStore',
                'toStore',
                'stockTransferInventories.inventory.item',
                'stockTransferInventories.inventory.item.image',
                'stockTransferInventories.inventory.item.category',
                'stockTransferInventories.inventory.item.type',
                'stockTransferInventories.inventory.item.colour',
            ]);

            $stock_transfer_resource = (new StockTransferResource($stock_transfer))->additional([
                'message' => 'Stock transfer created successfully',
            ]);

            DB::commit();

            return $stock_transfer_resource;

        } catch (\Throwable $th) {

            DB::rollBack();

            Log::error($th);

            return response()->json([
                'error' => $th->getMessage(),
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(StockTransfer $stock_transfer)
    {
        $stock_transfer->applyRequestIncludesAndAppends();

        $stock_transfer->loadCount([
            'inventories',
            'stockTransferInventories',
        ])->load([
            'receiver',
            'sender',
            'fromStore',
            'toStore',
            'stockTransferInventories.inventory.item',
            'stockTransferInventories.inventory.item.image',
            'stockTransferInventories.inventory.item.category',
            'stockTransferInventories.inventory.item.type',
            'stockTransferInventories.inventory.item.colour',
        ]);

        $stock_transfer_resource = (new StockTransferResource($stock_transfer))->additional([
            'message' => 'Stock transfer retrieved successfully',
        ]);

        return $stock_transfer_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStockTransferRequest $request, StockTransfer $stock_transfer)
    {
        $validated = $request->validated();

        try {

            DB::beginTransaction();

            $stock_transfer->update($validated);

            $stock_transfer_inventories = data_get($validated, 'stock_transfer_inventories', []);
            $entries = collect([]);
            foreach ($stock_transfer_inventories as $stock_inventory) {
                $entries->push([
                    'id' => $stock_inventory['id'] ?? Str::uuid(),
                    'inventory_id' => $stock_inventory['inventory_id'],
                    'quantity' => $stock_inventory['quantity'],
                    'stock_transfer_id' => $stock_transfer->id,
                ]);
            }

            if ($entries->isNotEmpty()) {
                StockTransferInventory::upsert(
                    $entries->toArray(),
                    uniqueBy: ['stock_transfer_id', 'inventory_id'],
                    update: ['quantity']
                );
            }

            $stock_transfer->load([
                'receiver',
                'sender',
                'fromStore',
                'toStore',
                'stockTransferInventories.inventory.item',
                'stockTransferInventories.inventory.item.image',
                'stockTransferInventories.inventory.item.category',
                'stockTransferInventories.inventory.item.type',
                'stockTransferInventories.inventory.item.colour',
            ]);

            $stock_transfer_resource = (new StockTransferResource($stock_transfer))->additional([
                'message' => 'Stock transfer updated successfully',
            ]);

            DB::commit();

            return $stock_transfer_resource;

        } catch (\Throwable $th) {

            DB::rollBack();

            Log::error($th);

            return response()->json([
                'error' => $th->getMessage(),
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StockTransfer $stock_transfer)
    {
        $stock_transfer->delete();

        $stock_transfer_resource = (new StockTransferResource(null))->additional([
            'message' => 'Stock transfer deleted successfully',
        ]);

        return $stock_transfer_resource;
    }

    /**
     * Relation the specified resource in storage.
     */
    public function destroyStockTransferInventory(StockTransfer $stock_transfer, StockTransferInventory $stock_transfer_inventory)
    {
        Gate::authorize('update', $stock_transfer);

        DB::beginTransaction();

        try {

            if (! $stock_transfer->stockTransferInventories()->where('id', $stock_transfer_inventory->id)->exists()) {
                $stock_transfer_resource = (new StockTransferResource($stock_transfer))->additional([
                    'message' => 'stock transfer inventory deleted successfully',
                ]);

                return $stock_transfer_resource;
            }

            $stock_transfer_inventory->inventory->increment('quantity', $stock_transfer_inventory->quantity);
            $stock_transfer->stockTransferInventories()->where('id', $stock_transfer_inventory->id)->delete();

            $stock_transfer->save();
            $stock_transfer->load('stockTransferInventories.inventory.item');

            DB::commit();

            $stock_transfer_resource = (new StockTransferResource($stock_transfer))->additional([
                'message' => 'Stock transfer inventory deleted successfully',
            ]);

            return $stock_transfer_resource;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->json([
                'message' => 'Failed to delete stock transfer inventory.',
                'errors' => ['delete_stock_transfer_inventory' => $e->getMessage()],
            ], 500);
        }

    }

    /**
     * Dispatch a stock transfer.
     *
     * @param  \App\Models\StockTransfer  $stock_transfer  The stock transfer instance to be accepted.
     * @return \App\Http\Resources\StockTransferResource The resource representation of the accepted stock transfer.
     */
    public function dispatch(StockTransfer $stock_transfer)
    {
        Gate::authorize('dispatch', $stock_transfer);

        $stock_transfer->status = Status::DISPATCHED->value;
        $stock_transfer->dispatched_at = now();
        $stock_transfer->save();

        if ($stock_transfer->stockTransferInventories->isNotEmpty()) {
            defer(function () use ($stock_transfer) {
                foreach ($stock_transfer->stockTransferInventories as $stock_transfer_inventory) {
                    $stock_transfer_inventory->inventory->decrement('quantity', $stock_transfer_inventory->quantity);
                }
            });
        }

        $users = User::whereHas('staff', function ($query) use ($stock_transfer) {
            $query->where('staff.store_id', $stock_transfer->to_store_id);
        })
            ->where(function ($query) {
                $query->permission('stock_transfer.receive')
                    ->orWhereHas('roles', function ($query) {
                        $query->where('name', 'admin');
                    });
            })
            ->get();

        if ($users->isNotEmpty()) {
            defer(fn () => Notification::send($users, new StockTransferDispatchedNotification($stock_transfer)));
        }

        $stock_transfer_resource = (new StockTransferResource($stock_transfer))->additional([
            'message' => 'Stock transfer dispatched successfully',
        ]);

        return $stock_transfer_resource;
    }

    /**
     * Accepts a stock transfer.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request.
     * @param  \App\Models\StockTransfer  $stock_transfer  The stock transfer instance to be accepted.
     * @return \App\Http\Resources\StockTransferResource The resource representation of the accepted stock transfer.
     */
    public function accept(Request $request, StockTransfer $stock_transfer)
    {
        Gate::authorize('accept', $stock_transfer);

        $toStore = $stock_transfer->toStore;
        $inventories = $toStore->inventories()
            ->whereIn('product_id', $stock_transfer->inventories()->pluck('product_id')->toArray())
            ->get();

        $entry = collect([]);

        foreach ($stock_transfer->stockTransferInventories as $stock_transfer_inventory) {

            $inventory = $inventories->firstWhere('product_id', $stock_transfer_inventory->inventory->product_id);
            $quantiy = $inventory ? $inventory->quantity + $stock_transfer_inventory->quantity : $stock_transfer_inventory->quantity;
            $entry->push([
                'store_id' => $stock_transfer->to_store_id,
                'product_id' => $stock_transfer_inventory->inventory->product_id,
                'quantity' => $quantiy,
            ]);
        }

        try {

            DB::beginTransaction();

            $stock_transfer->status = Status::ACCEPTED->value;
            $stock_transfer->accepted_at = now();

            $stock_transfer->save();

            $start_time = now();

            if ($entry->isNotEmpty()) {
                $toStore->inventories()->upsert(
                    $entry->toArray(),
                    uniqueBy: ['store_id', 'product_id'],
                    update: ['quantity']
                );
            }

            $end_time = now();

            $start_time_string = $start_time->toDateTimeString();
            $end_time_string = $end_time->toDateTimeString();

            $inventories = Inventory::whereBetween('created_at', [
                $start_time_string,  $end_time_string,
            ])
                ->orwhereBetween('updated_at', [
                    $start_time_string,  $end_time_string,
                ])
                ->lazy();

            defer(function () use ($inventories) {
                if ($inventories->isEmpty()) {
                    return;
                }
                foreach ($inventories as $inventory) {
                    if ($inventory->created_at == $inventory->updated_at) {
                        event('eloquent.created: '.$inventory::class, $inventory);
                    } else {
                        event('eloquent.updated: '.$inventory::class, $inventory);
                    }
                }
            });

            DB::commit();

            if ($stock_transfer->sender) {
                defer(fn () => $stock_transfer->sender->notify(new StockTransferReceivedNotification($stock_transfer)));
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->json([
                'message' => 'Failed to accept stock transfer.',
                'errors' => ['accept_stock_transfer' => $e->getMessage()],
            ], 500);
        }

        $stock_transfer_resource = (new StockTransferResource($stock_transfer))->additional([
            'message' => 'Stock transfer received successfully',
        ]);

        return $stock_transfer_resource;
    }

    /**
     * Reject a stock transfer.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request.
     * @param  \App\Models\StockTransfer  $stock_transfer  The stock transfer instance to be rejected.
     * @return \App\Http\Resources\StockTransferResource The resource representation of the rejected stock transfer.
     */
    public function reject(Request $request, StockTransfer $stock_transfer)
    {
        Gate::authorize('reject', $stock_transfer);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:255',
        ]);
        $stock_transfer->accepted_at = null;
        $stock_transfer->status = Status::REJECTED->value;
        $stock_transfer->rejected_at = now();
        $stock_transfer->rejection_reason = $validated['rejection_reason'];
        $stock_transfer->save();

        if ($stock_transfer->sender) {
            defer(fn () => $stock_transfer->sender->notify(new StockTransferRejectedNotification($stock_transfer)));
        }

        $stock_transfer_resource = (new StockTransferResource($stock_transfer))->additional([
            'message' => 'Stock transfer rejected successfully',
        ]);

        return $stock_transfer_resource;
    }
}
