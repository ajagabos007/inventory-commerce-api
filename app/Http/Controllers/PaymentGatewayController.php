<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentGatewayRequest;
use App\Http\Requests\UpdatePaymentGatewayRequest;
use App\Http\Resources\PaymentGatewayResource;
use App\Models\PaymentGateway;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PaymentGatewayController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/invetories
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;


        $paymentGateways = QueryBuilder::for(PaymentGateway::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'mode',
                AllowedFilter::exact('is_default'),
                AllowedFilter::scope('enabled', 'enabled'),
            ])
            ->allowedIncludes([
                'configs'
            ]);

        $paymentGateways->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $paymentGateways = $paymentGateways->paginate($perPage)
                ->appends(request()->query());

        } else {
            $paymentGateways = $paymentGateways->get();
        }

        return PaymentGatewayResource::collection($paymentGateways)->additional([
            'status' => 'success',
            'message' => 'Payment gateways retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentGatewayRequest $request) {}

    /**
     * Display the specified resource.
     */
    public function show(PaymentGateway $paymentGateway)
    {
        $paymentGateway->loadFromRequest();

        return (new PaymentGatewayResource($paymentGateway))->additional([
            'message' => 'Payment gateway retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentGatewayRequest $request, PaymentGateway $paymentGateway)
    {
        $validated = $request->validated();
        $isDisabled = boolVal(data_get($validated, 'is_disabled'));
        $validated['disabled_at'] = $isDisabled ? now() : null;

        $paymentGateway->update($validated);

        if (array_key_exists('logo', $validated)) {
            $paymentGateway->updateLogo($validated['logo']);
            $paymentGateway->append('logo_url');

        }

        return (new PaymentGatewayResource($paymentGateway))->additional([
            'message' => 'PaymentGateway updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentGateway $paymentGateway)
    {
        //
    }
}
