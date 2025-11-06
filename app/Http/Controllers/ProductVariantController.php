<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductVariantRequest;
use App\Http\Requests\SyncAttributeValuesRequest;
use App\Http\Requests\UpdateProductVariantRequest;
use App\Http\Requests\UploadImageRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Attachment;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
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
        $productVariants = QueryBuilder::for(ProductVariant::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes([
                'images',
                'product.images',
                'product.categories',
                'product.attributeValues.attribute',
                'attributeValues.attribute',
            ])
            ->allowedFilters([
                'categories.id',
                'attributeValues.id',
                'attributeValues.attribute.id',

                'attributeValues.id',
                'attributeValues.attribute.id',
                'product.categories.id',
                'product.attributeValues.id',
                'product.attributeValues.attribute.id',
                AllowedFilter::scope('low_stock', 'lowStock'),
                AllowedFilter::scope('out_of_stock', 'outOfStock'),

                // Popular/Trending filters
                AllowedFilter::scope('popular', 'popular'),
                AllowedFilter::scope('trending', 'trending'),
                AllowedFilter::scope('has_sales', 'hasSales'),
                AllowedFilter::scope('top_selling', 'topSelling'),

                // Date range for popularity
                AllowedFilter::scope('popular_from', 'popularFrom'),
                AllowedFilter::scope('popular_to', 'popularTo'),
            ])
            ->when(request()->filled('q'),function($query){
                $query->search(request()->q);
            })
            ->when(! in_array(request()->paginate, [false, 'false', 0, '0', 'no'], true), function ($query) {
                $perPage = ! is_numeric(request()->per_page) ? 15 : max(intval(request()->per_page), 1);

                return $query->paginate($perPage)
                    ->appends(request()->query());
            }, function($query){
                return $query->get();
            });

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

            if (array_key_exists('images', $validated)) {
                foreach ($validated['images'] as $image) {
                    DB::beginTransaction();

                    try {
                        $productVariant->updateUploadedBase64File($image);
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        logger($e->getMessage());
                    }
                }
            }

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }

        $productVariant->load('attributeValues.attribute', 'product.attributeValues.attribute', 'images');

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

        return (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant retrieved successfully',
        ]);
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

    /**
     *  Upload the specified resource image in storage.
     *
     * @throws \Exception
     */
    public function uploadImage(UploadImageRequest $request, ProductVariant $productVariant): ProductVariantResource
    {
        $validated = $request->validated();
        $productVariant->updateUploadedBase64File($validated['image']);

        $productVariant->load('images');

        return (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant\'s image uploaded successfully',
        ]);
    }

    /**
     *  Update the specified resource image in storage.
     *
     * @throws \Exception
     */
    public function updateImage(UploadImageRequest $request, ProductVariant $productVariant, Attachment $image): ProductVariantResource
    {
        $validated = $request->validated();
        $productVariant->updateUploadedBase64File($validated['image'], ['file_name' => $image->name]);

        $productVariant->load('images');

        return (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant\'s image updated successfully',
        ]);
    }

    /**
     *  Delete the specified resource image in storage.
     *
     * @throws \Exception
     */
    public function deleteImage(ProductVariant $productVariant, Attachment $image): ProductVariantResource
    {
        $productVariant->detachAttachment($image);

        $productVariant->load('images');

        return (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant\'s image delete successfully',
        ]);
    }

    /**
     * Sync the specified resource attribute values in storage.
     */
    public function addAttributeValue(Request $request, ProductVariant $productVariant, AttributeValue $attributeValue)
    {
        $productVariant->attributeValues()->syncWithoutDetaching($attributeValue->id);

        $productVariant->load('attributeValues.attribute');

        return (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant\'s attribute value added successfully',
        ]);
    }

    /**
     * Sync the specified resource attribute values in storage.
     */
    public function removeAttributeValue(Request $request, ProductVariant $productVariant, AttributeValue $attributeValue)
    {
        $productVariant->attributeValues()->detach($attributeValue->id);

        $productVariant->load('attributeValues.attribute');

        return (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant\'s attribute value removed successfully',
        ]);
    }

    /**
     * Sync the specified resource attribute values in storage.
     */
    public function syncAttributeValues(SyncAttributeValuesRequest $request, ProductVariant $productVariant)
    {
        $validated = $request->validated();

        $attributeValues = data_get($validated, 'attribute_value_ids', []);

        if (is_array($attributeValues)) {
            $attributeValues = array_filter($attributeValues, function ($value) {
                return ! blank($value);
            });
            $productVariant->attributeValues()->sync($attributeValues);
        }

        $productVariant->load('attributeValues.attribute');

        return (new ProductVariantResource($productVariant))->additional([
            'message' => 'Product variant\'s attribute value sync successfully',
        ]);
    }
}
