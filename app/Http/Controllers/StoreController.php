<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Spatie\QueryBuilder\AllowedFilter;
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
                AllowedFilter::exact('is_warehouse'),
            ])
            ->allowedIncludes([
            ]);

        $stores->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $stores = $stores->paginate($perPage)
                ->appends(request()->query());

        } else {
            $stores = $stores->get();
        }

        return StoreResource::collection($stores)->additional([
            'status' => 'success',
            'message' => 'Stores retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoreRequest $request)
    {
        $validated = $request->validated();
        $store = Store::create($validated);

        return (new StoreResource($store))->additional([
            'message' => 'Store created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Store $store)
    {
        $store->loadFromRequest();

        $store_resource = (new StoreResource($store))->additional([
            'message' => 'Store retrieved successfully',
        ]);

        return $store_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStoreRequest $request, Store $store): StoreResource
    {
        $validated = $request->validated();

        $store->update($validated);

        return (new StoreResource($store))->additional([
            'message' => 'Store updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Store $store): StoreResource
    {
        $store->delete();

        return (new StoreResource(null))->additional([
            'message' => 'Store deleted successfully',
        ]);
    }
}
