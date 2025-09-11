<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductVariantRequest;
use App\Http\Requests\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class ProductVariantController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(ProductVariant::class, 'product_variant');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/ProductVariants
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $productVariants = QueryBuilder::for(ProductVariant::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes([
                'product',
                'product.categories',
                'product.attributeValues',
                'product.attributeValues.attribute',
                'attributeValues',
                'attributeValues.attribute',
            ])
            ->allowedFilters([
                'attributeValues.id',
                'attributeValues.attribute.id',
                'product.attributeValues.id',
                'product.attributeValues.attribute.id',
            ]);

        $productVariants->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $productVariants = $productVariants->paginate($perPage)
                ->appends(request()->query());

        } else {
            $productVariants = $productVariants->get();
        }

        return ProductVariantResource::collection($productVariants)->additional([
            'status' => 'success',
            'message' => 'Product variants retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductVariantRequest $request)
    {
        $validated = $request->validated();
        try {
            DB::beginTransaction();

            $productVariant = ProductVariant::create($validated);

            if (array_key_exists('attribute_value_ids', $validated) && is_array($validated['attribute_value_ids'])) {
                $attributeValues = AttributeValue::whereIn('id', $validated['attribute_value_ids'])->pluck('id');

                if ($attributeValues->isNotEmpty()) {
                    $productVariant->attributeValues()->attach($attributeValues);
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }

        $productVariant->load('attributeValues.attribute', 'product.attributeValues.attribute');

        return (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant created successfully',
        ]);

    }

    /**
     * Display the specified resource.
     */
    public function show(ProductVariant $productVariant)
    {
        $productVariant->loadFromRequest();

        $productVariant_resource = (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant retrieved successfully',
        ]);

        return $productVariant_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductVariantRequest $request, ProductVariant $productVariant)
    {
        $validated = $request->validated();

        $productVariant->update($validated);

        return (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductVariant $productVariant)
    {
        $productVariant->delete();

        return (new ProductVariantResource(null))->additional([
            'message' => 'Product variant deleted successfully',
        ]);
    }
}
