<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDailyGoldPriceRequest;
use App\Http\Requests\UpdateDailyGoldPriceRequest;
use App\Http\Resources\DailyGoldPriceResource;
use App\Models\DailyGoldPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DailyGoldPriceController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(DailyGoldPrice::class, 'daily_gold_price');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/stores
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $daily_gold_prices = QueryBuilder::for(DailyGoldPrice::class)
            ->defaultSort('-recorded_on')
            ->allowedSorts(
                'price_per_gram',
                'recorded_on',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'category_id',
                'price_per_gram',
                'recorded_on',
                AllowedFilter::scope('period'),
            ])
            ->allowedIncludes([
                'category',
            ]);

        if (request()->has('q')) {
            $daily_gold_prices->where(function ($query) {
                $table_cols_key = $query->getModel()->getTable().'_column_listing';

                $cols = Cache::rememberForever($table_cols_key, function () use ($query) {
                    return Schema::getColumnListing($query->getModel()->getTable());
                });

                $query->where(function ($subQuery) use ($cols) {
                    foreach ($cols as $index => $col) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $subQuery->{$method}($col, 'LIKE', '%'.request()->q.'%');
                    }
                });
            })
                ->orWhereHas('category', function ($query) {
                    $table_cols_key = $query->getModel()->getTable().'_column_listing';

                    $cols = Cache::rememberForever($table_cols_key, function () use ($query) {
                        return Schema::getColumnListing($query->getModel()->getTable());
                    });

                    $query->where(function ($subQuery) use ($cols) {
                        foreach ($cols as $index => $col) {
                            $method = $index === 0 ? 'where' : 'orWhere';
                            $subQuery->{$method}($col, 'LIKE', '%'.request()->q.'%');
                        }
                    });
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

            $daily_gold_prices = $daily_gold_prices->paginate($perPage)
                ->appends(request()->query());

        } else {
            $daily_gold_prices = $daily_gold_prices->get();
        }

        $daily_gold_prices_collection = DailyGoldPriceResource::collection($daily_gold_prices)->additional([
            'status' => 'success',
            'message' => 'Daily gold prices retrieved successfully',
        ]);

        return $daily_gold_prices_collection;
    }

    /**
     * DailyGoldPrice a newly created resource in storage.
     */
    public function store(StoreDailyGoldPriceRequest $request)
    {
        $validated = $request->validated();
        $daily_gold_price = DailyGoldPrice::create($validated);

        $daily_gold_price_resource = (new DailyGoldPriceResource($daily_gold_price))->additional([
            'message' => 'Daily gold price created successfully',
        ]);

        return $daily_gold_price_resource;
    }

    /**
     * Display the specified resource.
     */
    public function show(DailyGoldPrice $daily_gold_price)
    {
        $daily_gold_price->applyRequestIncludesAndAppends();

        $daily_gold_price_resource = (new DailyGoldPriceResource($daily_gold_price))->additional([
            'message' => 'Daily gold price retrieved successfully',
        ]);

        return $daily_gold_price_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDailyGoldPriceRequest $request, DailyGoldPrice $daily_gold_price)
    {
        $validated = $request->validated();

        $daily_gold_price->update($validated);

        $daily_gold_price_resource = (new DailyGoldPriceResource($daily_gold_price))->additional([
            'message' => 'Daily gold price updated successfully',
        ]);

        return $daily_gold_price_resource;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DailyGoldPrice $daily_gold_price)
    {
        $daily_gold_price->delete();

        $daily_gold_price_resource = (new DailyGoldPriceResource(null))->additional([
            'message' => 'Daily gold price deleted successfully',
        ]);

        return $daily_gold_price_resource;
    }
}
