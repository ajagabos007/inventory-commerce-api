<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTypeRequest;
use App\Http\Requests\UpdateTypeRequest;
use App\Http\Resources\TypeResource;
use App\Models\Type;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

class TypeController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Type::class, 'type');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/types
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $types = QueryBuilder::for(type::class)
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
                'parentType',
                AllowedInclude::count('productsCount'),
            ]);

        if (request()->has('q')) {
            $types->where(function ($query) {
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
            if (! is_numeric($perPage)) {
                $perPage = 15;
            } else {
                $perPage = intval($perPage);
                $perPage = $perPage >= 1 ? $perPage : 15;
            }

            $types = $types->paginate($perPage)
                ->appends(request()->query());

        } else {
            $types = $types->get();
        }

        $types_collection = TypeResource::collection($types)->additional([
            'status' => 'success',
            'message' => 'Types retrieved successfully',
        ]);

        return $types_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTypeRequest $request)
    {
        $validated = $request->validated();
        $type = Type::create($validated);

        $type_resource = (new TypeResource($type))->additional([
            'message' => 'Type created successfully',
        ]);

        return $type_resource;
    }

    /**
     * Display the specified resource.
     */
    public function show(Type $type)
    {
        $type->applyRequestIncludesAndAppends();

        $type_resource = (new TypeResource($type))->additional([
            'message' => 'Type retrieved successfully',
        ]);

        return $type_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTypeRequest $request, Type $type)
    {
        $validated = $request->validated();

        // unset updating parent type if same as the type
        if (array_key_exists('parent_type_id', $validated) && $validated['parent_type_id'] == $type->id) {
            unset($validated['parent_type_id']);
        }

        $type->update($validated);

        $type_resource = (new TypeResource($type))->additional([
            'message' => 'Type updated successfully',
        ]);

        return $type_resource;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Type $type)
    {
        $type->delete();

        $type_resource = (new TypeResource(null))->additional([
            'message' => 'Type deleted successfully',
        ]);

        return $type_resource;
    }
}
