<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreColourRequest;
use App\Http\Requests\UpdateColourRequest;
use App\Http\Resources\ColourResource;
use App\Models\Colour;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

class ColourController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Colour::class, 'colour');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/colours
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $per_page = request()->has('per_page') ? request()->per_page : 15;

        $colours = QueryBuilder::for(Colour::class)
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
                AllowedInclude::count('productsCount'),
            ]);

        if (request()->has('q')) {
            $colours->where(function ($query) {
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

            $colours = $colours->paginate($per_page)
                ->appends(request()->query());

        } else {
            $colours = $colours->get();
        }

        $colours_collection = ColourResource::collection($colours)->additional([
            'status' => 'success',
            'message' => 'Colours retrieved successfully',
        ]);

        return $colours_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreColourRequest $request)
    {
        $validated = $request->validated();
        $colour = Colour::create($validated);

        $colour->applyRequestIncludesAndAppends();

        $colour_resource = (new ColourResource($colour))->additional([
            'message' => 'Colour created successfully',
        ]);

        return $colour_resource;
    }

    /**
     * Display the specified resource.
     */
    public function show(Colour $colour)
    {
        $colour->applyRequestIncludesAndAppends();

        $colour_resource = (new ColourResource($colour))->additional([
            'message' => 'Colour retrieved successfully',
        ]);

        return $colour_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateColourRequest $request, Colour $colour)
    {
        $validated = $request->validated();
        $colour->update($validated);

        $colour->applyRequestIncludesAndAppends();

        $colour_resource = (new ColourResource($colour))->additional([
            'message' => 'Colour updated successfully',
        ]);

        return $colour_resource;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Colour $colour)
    {
        $colour->delete();

        $colour_resource = (new ColourResource(null))->additional([
            'message' => 'Colour deleted successfully',
        ]);

        return $colour_resource;
    }
}
