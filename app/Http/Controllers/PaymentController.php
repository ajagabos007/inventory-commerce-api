<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PaymentController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Payment::class, 'payment');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/payments
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $payments = QueryBuilder::for(Payment::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes([
                'payables',
                'user',
            ])
            ->allowedFilters([
                'user_id',
                AllowedFilter::scope('is_paid', 'isPaid'),
                AllowedFilter::scope('is_verified', 'isVerified'),
            ])
            ->when(request()->filled('q'), function ($query) {
                $query->search(request()->q);
            });


        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $payments = $payments->paginate($perPage)
                ->appends(request()->query());

        } else {
            $payments = $payments->get();
        }

        return PaymentResource::collection($payments)->additional([
            'status' => 'success',
            'message' => 'Payments retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentRequest $request): \Illuminate\Http\JsonResponse|PaymentResource
    {
      return (new PaymentResource(null));
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment): PaymentResource
    {
        $payment->loadFromRequest();

        return (new PaymentResource($payment))->additional([
            'message' => 'Payment retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentRequest $request, Payment $payment): PaymentResource
    {
        $validated = $request->validated();

        $payment->update($validated);

        return (new PaymentResource($payment))->additional([
            'message' => 'Payment updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment): PaymentResource
    {
        $payment->delete();

        return (new PaymentResource(null))->additional([
            'message' => 'Payment deleted successfully',
        ]);
    }
}
