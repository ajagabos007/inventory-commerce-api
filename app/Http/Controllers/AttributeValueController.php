<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttributeValueRequest;
use App\Http\Requests\UpdateAttributeValueRequest;
use App\Http\Resources\AttributeValueResource;
use App\Models\AttributeValue;
use Spatie\QueryBuilder\QueryBuilder;

class AttributeValueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $attributeValues = QueryBuilder::for(AttributeValue::class)
            ->defaultSort('value')
            ->allowedSorts(
                'value',
                'display_value',
                'sort_order',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'value',
                'display_value',
                'sort_order',
                'attribute_id',
            ])
            ->allowedIncludes([
                'attribute',
            ]);

        $attributeValues->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $attributeValues = $attributeValues->paginate($perPage)
                ->appends(request()->query());

        } else {
            $attributeValues = $attributeValues->get();
        }

        return AttributeValueResource::collection($attributeValues)->additional([
            'status' => 'success',
            'message' => 'Attribute values retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAttributeValueRequest $request)
    {
        $validated = $request->validated();

        $attributeValue = AttributeValue::create($validated);

        return (new AttributeValueResource($attributeValue))->additional([
            'message' => 'Attribute value created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(AttributeValue $attributeValue)
    {
        $attributeValue->loadFromRequest();

        return (new AttributeValueResource($attributeValue))->additional([
            'message' => 'Attribute value retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAttributeValueRequest $request, AttributeValue $attributeValue)
    {
        $validated = $request->validated();

        $attributeValue->update($validated);

        return (new AttributeValueResource($attributeValue))->additional([
            'message' => 'Attribute value updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AttributeValue $attributeValue)
    {
        $attributeValue->delete();

        return (new AttributeValueResource(null))->additional([
            'message' => 'Attribute value deleted successfully',
        ]);
    }
}
