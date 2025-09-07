<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryTransferRequest;
use App\Http\Requests\UpdateInventoryTransferRequest;
use App\Http\Resources\InventoryTransferResource;
use App\Models\InventoryTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryTransferController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(InventoryTransfer::class, 'staff');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/staffs
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $per_page = request()->has('per_page') ? request()->per_page : 15;

        $inventory_transfers = QueryBuilder::for(InventoryTransfer::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'driver_name',
                'driver_phone_number',
                'transfer_date',
                'status',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'sender_id',
                'receiver_id',
                'transfer_date',
                'status',
            ])
            ->allowedIncludes([
                'receiver.user',
                'sender.user',
                'store',
                'inventory.store',
                'inventory.item.category',

            ]);

        if (request()->has('q')) {
            $searchTerm = '%'.request()->q.'%';

            $inventory_transfers->where(function ($query) use ($searchTerm) {
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
                ->orWhereHas('sender', function ($query) use ($searchTerm) {
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
                ->orWhereHas('receiver', function ($query) use ($searchTerm) {
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

            $inventory_transfers = $inventory_transfers->paginate($per_page)
                ->appends(request()->query());

        } else {
            $inventory_transfers = $inventory_transfers->get();
        }

        $inventory_transfers_collection = InventoryTransferResource::collection($inventory_transfers)->additional([
            'status' => 'success',
            'message' => 'InventoryTransfers retrieved successfully',
        ]);

        return $inventory_transfers_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventoryTransferRequest $request)
    {
        $validated = $request->validated();

        try {

            DB::beginTransaction();

            $inventory_transfer = InventoryTransfer::create($validated);

            $inventory_transfer_resource = (new InventoryTransferResource($inventory_transfer))->additional([
                'message' => 'Stock transfer created successfully',
            ]);

            DB::commit();

            return $inventory_transfer_resource;

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
    public function show(InventoryTransfer $inventory_transfer)
    {
        $inventory_transfer->applyRequestIncludesAndAppends();

        $inventory_transfer_resource = (new InventoryTransferResource($inventory_transfer))->additional([
            'message' => 'Stock transfer retrieved successfully',
        ]);

        return $inventory_transfer_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryTransferRequest $request, InventoryTransfer $inventory_transfer)
    {
        $validated = $request->validated();

        try {

            DB::beginTransaction();

            $inventory_transfer->update($validated);

            $inventory_transfer_resource = (new InventoryTransferResource($inventory_transfer))->additional([
                'message' => 'Stock transfer updated successfully',
            ]);

            DB::commit();

            return $inventory_transfer_resource;

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
    public function destroy(InventoryTransfer $inventory_transfer)
    {
        $inventory_transfer->delete();

        $inventory_transfer_resource = (new InventoryTransferResource(null))->additional([
            'message' => 'Stock transfer deleted successfully',
        ]);

        return $inventory_transfer_resource;
    }

    /**
     * Accepts an inventory transfer request.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request.
     * @param  \App\Models\InventoryTransfer  $inventory_transfer  The inventory transfer instance to be accepted.
     * @return \App\Http\Resources\InventoryTransferResource The resource representation of the accepted inventory transfer.
     */
    public function accept(Request $request, InventoryTransfer $inventory_transfer)
    {
        $inventory_transfer->status = 'accepted';
        $inventory_transfer->rejection_reason = null;
        $inventory_transfer->save();

        $inventory_transfer_resource = (new InventoryTransferResource($inventory_transfer))->additional([
            'message' => 'New Stock accepted successfully',
        ]);

        return $inventory_transfer_resource;

    }

    /**
     * Rejects an inventory transfer request.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request instance containing the rejection reason.
     * @param  \App\Models\InventoryTransfer  $inventory_transfer  The inventory transfer instance to be rejected.
     * @return \App\Http\Resources\InventoryTransferResource The resource representation of the rejected inventory transfer.
     *
     * @throws \Illuminate\Validation\ValidationException If the validation of the rejection reason fails.
     */
    public function reject(Request $request, InventoryTransfer $inventory_transfer)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:191',
        ]);

        $inventory_transfer->status = 'accepted';
        $inventory_transfer->rejection_reason = $validated['rejection_reason'];
        $inventory_transfer->save();

        $inventory_transfer_resource = (new InventoryTransferResource($inventory_transfer))->additional([
            'message' => 'New Stock rejected successfully',
        ]);

        return $inventory_transfer_resource;
    }
}
