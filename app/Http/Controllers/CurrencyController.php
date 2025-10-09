<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CurrencyController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Currency::class, 'currency');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/currencies
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $currencies = QueryBuilder::for(Currency::class)
            ->defaultSort('code')
            ->allowedSorts(
                'name',
                'symbol',
                'code',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                AllowedFilter::exact('is_active'),
                AllowedFilter::scope('disabled', 'disabled'),
            ])
            ->allowedIncludes([

            ]);

        $currencies->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $currencies = $currencies->paginate($perPage)
                ->appends(request()->query());

        } else {
            $currencies = $currencies->get();
        }

        $currencies_collection = CurrencyResource::collection($currencies)->additional([
            'status' => 'success',
            'message' => 'Currencies retrieved successfully',
        ]);

        return $currencies_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCurrencyRequest $request)
    {
        $validated = $request->validated();
        $currency = Currency::create($validated);

        $currency->loadFromRequest();

        return (new CurrencyResource($currency))->additional([
            'message' => 'Currency created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Currency $currency)
    {
        $currency->loadFromRequest();

        return (new CurrencyResource($currency))->additional([
            'message' => 'Currency retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCurrencyRequest $request, Currency $currency)
    {
        $validated = $request->validated();
        $currency->update($validated);

        $currency->loadFromRequest();

        return (new CurrencyResource($currency))->additional([
            'message' => 'Currency updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Currency $currency)
    {
        $currency->delete();

        return (new CurrencyResource(null))->additional([
            'message' => 'Currency deleted successfully',
        ]);
    }
}
