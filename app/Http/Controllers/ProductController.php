<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\SyncAttributeValuesRequest;
use App\Http\Requests\SyncCategoriesRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UploadImageRequest;
use App\Http\Resources\ProductResource;
use App\Models\Attachment;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use App\Sorts\ProductPopularSort;
use App\Sorts\ProductTrendingSort;
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
                AllowedSort::custom('popular', new ProductPopularSort()),
                AllowedSort::custom('trending', new ProductTrendingSort()),
            )
            ->allowedIncludes([
                'images',
                'variants',
                'categories',
                'attributeValues',
            ])
            ->allowedFilters([
                'categories.id',
                'attributeValues.id',
                'attributeValues.attribute.id',
                'variants.id',
                'variants.sku',
                'variants.categories.id',
                'variants.attributeValues.id',
                AllowedFilter::scope('low_stock', 'lowStock'),
                AllowedFilter::scope('out_of_stock', 'outOfStock'),
                // Popular/Trending filters
                AllowedFilter::scope('popular', 'popular'),
                AllowedFilter::scope('trending', 'trending'),
                AllowedFilter::scope('has_sales', 'hasSales'),
                AllowedFilter::scope('top_selling', 'topSelling'),

                // Date range filters
                AllowedFilter::scope('popular_from', 'popularFrom'),
                AllowedFilter::scope('popular_to', 'popularTo'),
                AllowedFilter::scope('best_sellers_week', 'bestSellersThisWeek'),
                AllowedFilter::scope('best_sellers_month', 'bestSellersThisMonth'),

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
        $warehouse = Store::warehouses()->first();

        try {
            DB::beginTransaction();

            $product = Product::create($validated);

            if (array_key_exists('attribute_value_ids', $validated) && is_array($validated['attribute_value_ids'])) {
                $attributeValues = AttributeValue::whereIn('id', $validated['attribute_value_ids'])->pluck('id');

                if ($attributeValues->isNotEmpty()) {
                    $product->attributeValues()->attach($attributeValues);
                }
            }

            $hasVariants = array_key_exists('variants', $validated) && ! blank($validated['variants']);

            /**
             * Create product as a variant of itself
             */
            $variant = $product->variants()->create($validated);

            $variant->attributeValues()->attach($product->attributeValues()->pluck('attribute_values.id')->toArray());
            if ($warehouse) {
                $validated['store_id'] = $warehouse->id;
                $variant->inventories()->create($validated);
            }

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

            // Mass Create Variants
            if ($hasVariants) {
                foreach ($validated['variants'] as $validatedVariant) {
                    if (blank($validatedVariant)) {
                        continue;
                    }
                    DB::beginTransaction();

                    try {
                        $variant = $product->variants()->create($validatedVariant);

                        if (array_key_exists('attribute_value_ids', $validatedVariant) && is_array($validatedVariant['attribute_value_ids'])) {
                            $attributeValues = AttributeValue::whereIn('id', $validatedVariant['attribute_value_ids'])->pluck('id');

                            if ($attributeValues->isNotEmpty()) {
                                $variant->attributeValues()->attach($attributeValues);
                            }
                        }

                        if ($warehouse) {
                            $validatedVariant['store_id'] = $warehouse->id;
                            $variant->inventories()->create($validatedVariant);
                        }

                        DB::commit();
                        if (array_key_exists('images', $validatedVariant)) {
                            foreach ($validatedVariant['images'] as $image) {
                                DB::beginTransaction();

                                try {
                                    $variant->updateUploadedBase64File($image);
                                    DB::commit();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    logger($e->getMessage());
                                }
                            }
                        }

                    } catch (\Throwable $th) {
                        DB::rollBack();
                        logger($th->getMessage());

                        if ($product->variants()->count() == 0) {
                            $variant = $product->variants()->create($validated);
                            $variant->attributeValues()->attach($product->attributeValues()->pluck('id')->toArray());

                        }
                    }
                }
            }

        } catch (\Exception $e) {
            DB::rollBack();
            logger($e);

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

        return (new ProductResource($product))->additional([
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

        return (new ProductResource($product))->additional([
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

        return (new ProductResource($product))->additional([
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

        return (new ProductResource($product))->additional([
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

        return (new ProductResource($product))->additional([
            'message' => 'Product\'s attribute value sync successfully',
        ]);
    }

    /**
     *  Sync the specified resource attribute values in storage.
     */
    public function addCategory(Request $request, Product $product, Category $category): ProductResource
    {
        $product->categories()->syncWithoutDetaching($category->id);

        $product->load('categories');

        return (new ProductResource($product))->additional([
            'message' => 'Product added to category successfully',
        ]);
    }

    /**
     * Sync the specified resource attribute values in storage.
     */
    public function removeCategory(Request $request, Product $product, Category $category): ProductResource
    {
        $product->categories()->detach($category->id);

        $product->load('categories');

        return (new ProductResource($product))->additional([
            'message' => 'Product removed from category successfully',
        ]);
    }

    /**
     * Sync the specified resource categories in storage.
     */
    public function syncCategories(SyncCategoriesRequest $request, Product $product): ProductResource
    {
        $validated = $request->validated();

        $categories = data_get($validated, 'category_ids', []);

        if (is_array($categories)) {
            $categories = array_filter($categories, function ($value) {
                return ! blank($value);
            });
            $product->categories()->sync($categories);
        }

        $product->load('categories');

        return (new ProductResource($product))->additional([
            'message' => 'Product\'s categories sync successfully',
        ]);
    }
}
