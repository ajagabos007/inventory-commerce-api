<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentGatewayConfigRequest;
use App\Http\Requests\UpdatePaymentGatewayConfigRequest;
use App\Http\Resources\PaymentGatewayConfigResource;
use App\Models\PaymentGatewayConfig;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PaymentGatewayConfigController extends Controller
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

        $paymentGatewayConfigs = QueryBuilder::for(PaymentGatewayConfig::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'payment_gateway_id',
                'mode',
            ])
            ->allowedIncludes([
                'gateway'
            ]);

        $paymentGatewayConfigs->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $paymentGatewayConfigs = $paymentGatewayConfigs->paginate($perPage)
                ->appends(request()->query());

        } else {
            $paymentGatewayConfigs = $paymentGatewayConfigs->get();
        }

        return PaymentGatewayConfigResource::collection($paymentGatewayConfigs)->additional([
            'status' => 'success',
            'message' => 'Payment gateways retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentGatewayConfigRequest $request) {
        $validated = $request->validated();

        $paymentGatewayConfig = PaymentGatewayConfig::updateOrCreate([
            'payment_gateway_id' => $validated['payment_gateway_id'],
            'mode'  => $validated['mode'],
        ],$validated);

        return (new PaymentGatewayConfigResource($paymentGatewayConfig))->additional([
            'message' => 'Payment gateway config created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentGatewayConfig $paymentGatewayConfig)
    {
        $paymentGatewayConfig->loadFromRequest();

        return (new PaymentGatewayConfigResource($paymentGatewayConfig))->additional([
            'message' => 'Payment gateway config retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentGatewayConfigRequest $request, PaymentGatewayConfig $paymentGatewayConfig)
    {
        $validated = $request->validated();


        $paymentGatewayConfig->update($validated);

        return (new PaymentGatewayConfigResource($paymentGatewayConfig))->additional([
            'message' => 'Payment gateway config updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentGatewayConfig $paymentGatewayConfig)
    {
        $paymentGatewayConfig->delete();

        return (new PaymentGatewayConfigResource(null))
            ->additional([
                'message' => 'Payment gateway config deleted successfully',
            ]);
    }
}
