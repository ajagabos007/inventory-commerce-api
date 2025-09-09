<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttributeRequest;
use App\Http\Requests\UpdateAttributeRequest;
use App\Http\Resources\AttributeResource;
use App\Models\Attribute;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class AttributeController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Attribute::class, 'attribute');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/categories
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $attributes = QueryBuilder::for(attribute::class)
            ->defaultSort('name')
            ->allowedSorts(
                'name',
                'sort_order',
                'type',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'name',
                'slug',
                'type',
                'values.id',
            ])
            ->allowedIncludes([
                'values',
            ]);

        $attributes->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $attributes = $attributes->paginate($perPage)
                ->appends(request()->query());

        } else {
            $attributes = $attributes->get();
        }

        return AttributeResource::collection($attributes)->additional([
            'status' => 'success',
            'message' => 'Attributes retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAttributeRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $attribute = Attribute::create($validated);

            $values = data_get($validated, 'values', []);

            if (is_array($values)) {
                $prepare_values = [];
                foreach ($values as $key => $value) {
                    $prepare_values[] = [
                        'value' => $value,
                    ];
                }

                $attribute->values()->createMany($prepare_values);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }

        $attribute->load('values');

        return (new AttributeResource($attribute))->additional([
            'message' => 'Attribute created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Attribute $attribute)
    {
        $attribute->loadFromRequest();

        return (new AttributeResource($attribute))->additional([
            'message' => 'Attribute retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAttributeRequest $request, Attribute $attribute)
    {
        $validated = $request->validated();
        $attribute->update($validated);

        return (new AttributeResource($attribute))->additional([
            'message' => 'Attribute updated successfully',
        ]);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attribute $attribute)
    {
        $attribute->delete();

        return (new AttributeResource(null))->additional([
            'message' => 'Attribute deleted successfully',
        ]);
    }
}
