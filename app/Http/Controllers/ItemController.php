<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Product::class, 'item');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/products
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $per_page = request()->has('per_page') ? request()->per_page : 15;

        $products = QueryBuilder::for(Product::withSum('inventories', 'quantity'))
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('type_id'),
                AllowedFilter::exact('colour_id'),
            ])
            ->allowedIncludes([
                'cateogry',
                'type',
                'colour',
            ]);

        if (request()->has('q')) {
            $products->where(function ($query) {
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
            if (! is_numeric($per_page)) {
                $per_page = 15;
            } else {
                $per_page = intval($per_page);
                $per_page = $per_page >= 1 ? $per_page : 15;
            }

            $products = $products->paginate($per_page)
                ->appends(request()->query());

        } else {
            $products = $products->get();
        }

        $products_collection = ProductResource::collection($products)->additional([
            'status' => 'success',
            'message' => 'Products retrieved successfully',
        ]);

        return $products_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();
        $item = Product::create($validated);

        if (array_key_exists('upload_image', $validated)) {
            $item->updateUploadedBase64File($validated['upload_image']);

        }

        if ($store_hq = Store::warehouses()->first()) {
            $store_hq->products()->syncWithPivotValues([$item->id], [
                'quantity' => $validated['quantity'] ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $item_resource = (new ProductResource($item))->additional([
            'message' => 'Product created successfully',
        ]);

        return $item_resource;
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $item)
    {
        $item->applyRequestIncludesAndAppends();

        $item_resource = (new ProductResource($item))->additional([
            'message' => 'Product retrieved successfully',
        ]);

        return $item_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $item)
    {
        $validated = $request->validated();

        $item->update($validated);

        // if(array_key_exists('images', $validated))
        // {
        //     foreach($validated['images'] as $image)
        //     {
        //         $item->updateUploadedBase64File($image);
        //     }
        // }

        if (array_key_exists('upload_image', $validated)) {
            $item->detachAttachments(null);
            $item->updateUploadedBase64File($validated['upload_image']);

        }

        if (array_key_exists('quantity', $validated) && ($store_hq = Store::warehouses()->first())) {
            $store_hq->products()->syncWithPivotValues([$item->id], [
                'quantity' => $validated['quantity'] ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $item_resource = (new ProductResource($item))->additional([
            'message' => 'Product updated successfully',
        ]);

        return $item_resource;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $item)
    {
        $item->delete();

        $item_resource = (new ProductResource(null))->additional([
            'message' => 'Product deleted successfully',
        ]);

        return $item_resource;
    }
}
