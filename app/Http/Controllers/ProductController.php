<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
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
    public function store(StoreProductRequest $request)
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
    public function show(Product $product)
    {
        $product->loadFromRequest();

        $product_resource = (new ProductResource($product))->additional([
            'message' => 'Product retrieved successfully',
        ]);

        return $product_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
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
    public function destroy(Product $product)
    {
        $product->delete();

        return (new ProductResource(null))->additional([
            'message' => 'Product deleted successfully',
        ]);
    }
}
