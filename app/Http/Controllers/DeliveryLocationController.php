<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryLocationRequest;
use App\Http\Requests\UpdateDeliveryLocationRequest;
use App\Http\Resources\DeliveryLocationResource;
use App\Models\DeliveryLocation;
use Spatie\QueryBuilder\QueryBuilder;

class DeliveryLocationController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(DeliveryLocation::class, 'delivery_location');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/staffs
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $deliveryLocations = QueryBuilder::for(DeliveryLocation::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'driver_name',
                'driver_phone_number',
                'transfer_date',
                'status',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'sender_id',
                'receiver_id',
                'transfer_date',
                'status',
            ])
            ->allowedIncludes([
                'country',
                'state',
                'city',
            ])
            ->when(request()->filled('q'), function ($query) {
                $query->search(request()->q);
            })

            /**
             * Check if pagination is not disabled
             */
            ->when(! in_array(request()->paginate, [false, 'false', 0, '0', 'no'], true), function ($query) {
                $perPage = request()->per_page;
                $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

                return $query->paginate($perPage)
                    ->appends(request()->query());
            }, function ($query) {
                return $query->get();
            });

        return DeliveryLocationResource::collection($deliveryLocations)->additional([
            'status' => 'success',
            'message' => 'Delivery locations retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDeliveryLocationRequest $request)
    {
        $validated = $request->validated();
        $deliveryLocation = DeliveryLocation::create($validated);

        return (new DeliveryLocationResource($deliveryLocation))->additional([
            'message' => 'Delivery location created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(DeliveryLocation $deliveryLocation)
    {
        $deliveryLocation->loadFromRequest();

        return (new DeliveryLocationResource($deliveryLocation))->additional([
            'message' => 'Delivery location retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDeliveryLocationRequest $request, DeliveryLocation $deliveryLocation)
    {
        $validated = $request->validated();

        $deliveryLocation->update($validated);

        return (new DeliveryLocationResource($deliveryLocation))->additional([
            'message' => 'Delivery location updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeliveryLocation $deliveryLocation)
    {
        $deliveryLocation->delete();

        return (new DeliveryLocationResource(null))->additional([
            'message' => 'Delivery location deleted successfully',
        ]);
    }
}
