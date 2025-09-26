<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Spatie\QueryBuilder\QueryBuilder;

class SupplierController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Supplier::class, 'supplier');
    }

    /**
     * Display a listing of the resource.
     *serve
     *
     * @method GET|HEAD /api/Suppliers
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $suppliers = QueryBuilder::for(Supplier::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'name',
                'name',
                'email',
                'phone_number',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'user_id',
            ])
            ->allowedIncludes([
                'user',
                'user.roles.permissions',
                'user.roles',
            ]);

        $suppliers->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $suppliers = $suppliers->paginate($perPage)
                ->appends(request()->query());

        } else {
            $suppliers = $suppliers->get();
        }

        return SupplierResource::collection($suppliers)->additional([
            'status' => 'success',
            'message' => 'Suppliers retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request): SupplierResource
    {
        $validated = $request->validated();

        $supplier = Supplier::firstOrCreate($validated);

        return (new SupplierResource($supplier))->additional([
            'message' => 'Supplier created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier): SupplierResource
    {
        $supplier->loadFromRequest();

        return (new SupplierResource($supplier))->additional([
            'message' => 'Supplier retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $validated = $request->validated();
        $supplier->update($validated);

        return (new SupplierResource($supplier))->additional([
            'message' => 'Supplier updated successfully',
        ]);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier): SupplierResource
    {
        $supplier->delete();

        return (new SupplierResource(null))->additional([
            'message' => 'Supplier deleted successfully',
        ]);
    }
}
