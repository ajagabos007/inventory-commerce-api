<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrderController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Order::class, 'order');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/orders
     */
    public function index()
    {
        $orderQ = Order::query();
        //                    ->forUser(auth()->user());

        $orders = QueryBuilder::for($orderQ)
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
                AllowedFilter::scope('low_stock', 'lowStock'),
                AllowedFilter::scope('out_of_stock', 'outOfStock'),

            ])
            ->when(request()->filled('q'), function ($query) {
                $query->search(request()->q);
            })
            ->when(! in_array(request()->paginate, [false, 'false', 0, '0', 'no'], true), function ($query) {
                $perPage = request()->per_page;
                $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

                return $query->paginate($perPage)
                    ->appends(request()->query());
            }, function ($query) {
                return $query->get();
            });

        return OrderResource::collection($orders)->additional([
            'status' => 'success',
            'message' => 'Orders retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request): \Illuminate\Http\JsonResponse|OrderResource
    {
        return new OrderResource(null);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order): OrderResource
    {
        $order->loadFromRequest();

        return (new OrderResource($order))->additional([
            'message' => 'Order retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $validated = $request->validated();

        $order->update($validated);

        return (new OrderResource($order))->additional([
            'message' => 'Order updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order): OrderResource
    {
        $order->delete();

        return (new OrderResource(null))->additional([
            'message' => 'Order deleted successfully',
        ]);
    }
}
