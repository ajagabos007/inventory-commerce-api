<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
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
        $perPage = request()->has('per_page') ? request()->per_page : 15;

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
                'subCategories',
                AllowedInclude::count('productsCount'),
            ]);

        $categories->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $categories = $categories->paginate($perPage)
                ->appends(request()->query());

        } else {
            $categories = $categories->get();
        }

        return CategoryResource::collection($categories)->additional([
            'status' => 'success',
            'message' => 'Categories retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();
        $category = Category::create($validated);

        if (array_key_exists('image', $validated) && ! blank($validated['image'])) {
            $category->updateImage($validated['image']);
        }

        return (new CategoryResource($category))->additional([
            'message' => 'Category created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        $category->loadFromRequest();

        return (new CategoryResource($category))->additional([
            'message' => 'Category retrieved successfully',
        ]);
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

        if (array_key_exists('image', $validated)) {
            if (blank($validated['image'])) {
                $category->deleteImage();
            } else {
                $category->updateImage($validated['image']);
            }
        }

        return (new CategoryResource($category))->additional([
            'message' => 'Category updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return (new CategoryResource(null))->additional([
            'message' => 'Category deleted successfully',
        ]);
    }
}
