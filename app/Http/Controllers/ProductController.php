<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\SyncAttributeValuesRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UploadImageRequest;
use App\Http\Resources\ProductResource;
use App\Models\Attachment;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Product::class, 'product');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/products
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $products = QueryBuilder::for(Product::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes([
                'images',
                'variants',
                'categories',
                'attributeValues',
            ])
            ->allowedFilters([
                'variants.id',
                'variants.sku',
            ]);

        $products->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $products = $products->paginate($perPage)
                ->appends(request()->query());

        } else {
            $products = $products->get();
        }

        return ProductResource::collection($products)->additional([
            'status' => 'success',
            'message' => 'Products retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): \Illuminate\Http\JsonResponse|ProductResource
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $product = Product::create($validated);

            if (array_key_exists('attribute_value_ids', $validated) && is_array($validated['attribute_value_ids'])) {
                $attributeValues = AttributeValue::whereIn('id', $validated['attribute_value_ids'])->pluck('id');

                if ($attributeValues->isNotEmpty()) {
                    $product->attributeValues()->attach($attributeValues);
                }
            }

            $product->variants()->create($validated);

            if (array_key_exists('category_ids', $validated) && is_array($validated['category_ids'])) {
                $categories = Category::whereIn('id', $validated['category_ids'])->pluck('id');

                if ($categories->isNotEmpty()) {
                    $product->categories()->attach($categories);
                }
            }

            DB::commit();

            if (array_key_exists('images', $validated)) {
                foreach ($validated['images'] as $image) {
                    DB::beginTransaction();

                    try {
                        $product->updateUploadedBase64File($image);
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

        $product->load('attributeValues.attribute', 'variants', 'categories');

        return (new ProductResource($product))->additional([
            'message' => 'Product created successfully',
        ]);

    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): ProductResource
    {
        $product->loadFromRequest();

        return (new ProductResource($product))->additional([
            'message' => 'Product retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $validated = $request->validated();

        $product->update($validated);

        return (new ProductResource($product))->additional([
            'message' => 'Product updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): ProductResource
    {
        $product->delete();

        return (new ProductResource(null))->additional([
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     *  Upload the specified resource image in storage.
     *
     * @throws \Exception
     */
    public function uploadImage(UploadImageRequest $request, Product $product): ProductResource
    {
        $validated = $request->validated();
        $product->updateUploadedBase64File($validated['image']);

        $product->load('images');

        return new ProductResource($product)->additional([
            'message' => 'Product\'s image uploaded successfully',
        ]);
    }

    /**
     *  Update the specified resource image in storage.
     *
     * @throws \Exception
     */
    public function updateImage(UploadImageRequest $request, Product $product, Attachment $image): ProductResource
    {
        $validated = $request->validated();
        $product->updateUploadedBase64File($validated['image'], ['file_name' => $image->name]);

        $product->load('images');

        return new ProductResource($product)->additional([
            'message' => 'Product\'s image updated successfully',
        ]);
    }

    /**
     *  Delete the specified resource image in storage.
     *
     * @throws \Exception
     */
    public function deleteImage(Product $product, Attachment $image): ProductResource
    {
        $product->detachAttachment($image);

        $product->load('images');

        return new ProductResource($product)->additional([
            'message' => 'Product\'s image delete successfully',
        ]);
    }

    /**
     *  Sync the specified resource attribute values in storage.
     */
    public function addAttributeValue(Request $request, Product $product, AttributeValue $attributeValue): ProductResource
    {
        $product->attributeValues()->syncWithoutDetaching($attributeValue->id);

        $product->load('attributeValues.attribute');

        return (new ProductResource($product))->additional([
            'message' => 'Product\'s attribute value added successfully',
        ]);
    }

    /**
     * Sync the specified resource attribute values in storage.
     */
    public function removeAttributeValue(Request $request, Product $product, AttributeValue $attributeValue): ProductResource
    {
        $product->attributeValues()->detach($attributeValue->id);

        $product->load('attributeValues.attribute');

        return new ProductResource($product)->additional([
            'message' => 'Product\'s attribute value removed successfully',
        ]);
    }

    /**
     * Sync the specified resource attribute values in storage.
     */
    public function syncAttributeValues(SyncAttributeValuesRequest $request, Product $product): ProductResource
    {
        $validated = $request->validated();

        $attributeValues = data_get($validated, 'attribute_value_ids', []);

        if (is_array($attributeValues)) {
            $attributeValues = array_filter($attributeValues, function ($value) {
                return ! blank($value);
            });
            $product->attributeValues()->sync($attributeValues);
        }

        $product->load('attributeValues.attribute');

        return new ProductResource($product)->additional([
            'message' => 'Product\'s attribute value sync successfully',
        ]);
    }
}
