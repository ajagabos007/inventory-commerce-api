<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Category::class, 'category');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/categories
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $per_page = request()->has('per_page') ? request()->per_page : 15;

        $categories = QueryBuilder::for(category::class)
            ->defaultSort('name')
            ->allowedSorts(
                'name',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'name',
            ])
            ->allowedIncludes([
                'parentCategory',
                AllowedInclude::count('productsCount'),
            ]);

        if (request()->has('q')) {
            $categories->where(function ($query) {
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

            $categories = $categories->paginate($per_page)
                ->appends(request()->query());

        } else {
            $categories = $categories->get();
        }

        $categories_collection = CategoryResource::collection($categories)->additional([
            'status' => 'success',
            'message' => 'Categories retrieved successfully',
        ]);

        return $categories_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();
        $category = Category::create($validated);

        $category_resource = (new CategoryResource($category))->additional([
            'message' => 'Category created successfully',
        ]);

        return $category_resource;
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        $category->applyRequestIncludesAndAppends();

        $category_resource = (new CategoryResource($category))->additional([
            'message' => 'Category retrieved successfully',
        ]);

        return $category_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validated = $request->validated();

        // unset updating parent category if same as the category
        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] == $category->id) {
            unset($validated['parent_id']);
        }

        $category->update($validated);

        $category_resource = (new CategoryResource($category))->additional([
            'message' => 'Category updated successfully',
        ]);

        return $category_resource;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $category->delete();

        $category_resource = (new CategoryResource(null))->additional([
            'message' => 'Category deleted successfully',
        ]);

        return $category_resource;
    }
}
