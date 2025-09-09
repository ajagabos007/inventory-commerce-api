<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\QueryBuilder;

class StoreController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Store::class, 'store');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/stores
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $stores = QueryBuilder::for(Store::class)
            ->defaultSort('name')
            ->allowedSorts(
                'name',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'name',
            ])
            ->allowedIncludes([
                'manager',
            ]);

        if (request()->has('q')) {
            $stores->where(function ($query) {
                $table_cols_key = $query->getModel()->getTable().'_column_listing';

                if (Cache::has($table_cols_key)) {
                    $cols = Cache::get($table_cols_key);
                } else {
                    $cols = Schema::getColumnListing($query->getModel()->getTable());
                    Cache::put($table_cols_key, $cols);
                }

                $counter = 0;
                foreach ($cols as $col) {

                    if ($counter == 0) {
                        $query->where($col, 'LIKE', '%'.request()->q.'%');
                    } else {
                        $query->orWhere($col, 'LIKE', '%'.request()->q.'%');
                    }
                    $counter++;
                }
            })
                ->orWhereHas('manager', function ($query) {
                    $table_cols_key = $query->getModel()->getTable().'_column_listing';

                    if (Cache::has($table_cols_key)) {
                        $cols = Cache::get($table_cols_key);
                    } else {
                        $cols = Schema::getColumnListing($query->getModel()->getTable());
                        Cache::put($table_cols_key, $cols);
                    }

                    $counter = 0;
                    foreach ($cols as $col) {

                        if ($counter == 0) {
                            $query->where($col, 'LIKE', '%'.request()->q.'%');
                        } else {
                            $query->orWhere($col, 'LIKE', '%'.request()->q.'%');
                        }
                        $counter++;
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

            $stores = $stores->paginate($perPage)
                ->appends(request()->query());

        } else {
            $stores = $stores->get();
        }

        $stores_collection = StoreResource::collection($stores)->additional([
            'status' => 'success',
            'message' => 'Stores retrieved successfully',
        ]);

        return $stores_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoreRequest $request)
    {
        $validated = $request->validated();
        $store = Store::create($validated);
        $store->load(['manager.user']);

        $store_resource = (new StoreResource($store))->additional([
            'message' => 'Store created successfully',
        ]);

        return $store_resource;
    }

    /**
     * Display the specified resource.
     */
    public function show(Store $store)
    {
        $store->applyRequestIncludesAndAppends();

        $store_resource = (new StoreResource($store))->additional([
            'message' => 'Store retrieved successfully',
        ]);

        return $store_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStoreRequest $request, Store $store)
    {
        $validated = $request->validated();

        $store->update($validated);
        $store->load(['manager.user']);

        $store_resource = (new StoreResource($store))->additional([
            'message' => 'Store updated successfully',
        ]);

        return $store_resource;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Store $store)
    {
        $store->delete();

        $store_resource = (new StoreResource(null))->additional([
            'message' => 'Store deleted successfully',
        ]);

        return $store_resource;
    }
}
