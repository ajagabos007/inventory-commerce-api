<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\UpdateDiscountRequest;
use App\Http\Resources\DiscountResource;
use App\Models\Discount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DiscountController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Discount::class, 'discount');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/discounts
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $per_page = request()->has('per_page') ? request()->per_page : 15;

        $discounts = QueryBuilder::for(Discount::class)
            ->defaultSort('code')
            ->allowedSorts(
                'code',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'code',
                AllowedFilter::exact('is_active'),
            ])
            ->allowedIncludes([

            ]);

        if (request()->has('q')) {
            $discounts->where(function ($query) {
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

            $discounts = $discounts->paginate($per_page)
                ->appends(request()->query());

        } else {
            $discounts = $discounts->get();
        }

        $discounts_collection = DiscountResource::collection($discounts)->additional([
            'status' => 'success',
            'message' => 'Discounts retrieved successfully',
        ]);

        return $discounts_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDiscountRequest $request)
    {
        $validated = $request->validated();
        $discount = Discount::create($validated);

        $discount->applyRequestIncludesAndAppends();

        $discount_resource = (new DiscountResource($discount))->additional([
            'message' => 'Discount created successfully',
        ]);

        return $discount_resource;
    }

    /**
     * Display the specified resource.
     */
    public function show(Discount $discount)
    {
        $discount->applyRequestIncludesAndAppends();

        $discount_resource = (new DiscountResource($discount))->additional([
            'message' => 'Discount retrieved successfully',
        ]);

        return $discount_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDiscountRequest $request, Discount $discount)
    {
        $validated = $request->validated();
        $discount->update($validated);

        $discount->applyRequestIncludesAndAppends();

        $discount_resource = (new DiscountResource($discount))->additional([
            'message' => 'Discount updated successfully',
        ]);

        return $discount_resource;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Discount $discount)
    {
        $discount->delete();

        $discount_resource = (new DiscountResource(null))->additional([
            'message' => 'Discount deleted successfully',
        ]);

        return $discount_resource;
    }
}
