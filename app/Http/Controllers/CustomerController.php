<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Customer::class, 'customer');
    }

    /**
     * Display a listing of the resource.
     *serve
     *
     * @method GET|HEAD /api/customers
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $customers = QueryBuilder::for(Customer::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'name',
                'name',
                'email',
                'phone_number',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'user_id',
            ])
            ->allowedIncludes([
                'user',
                'user.roles.permissions',
                'user.roles',
            ]);

        $customers->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $customers = $customers->paginate($perPage)
                ->appends(request()->query());

        } else {
            $customers = $customers->get();
        }

        return CustomerResource::collection($customers)->additional([
            'status' => 'success',
            'message' => 'Customers retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request): CustomerResource
    {
        $validated = $request->validated();

        $customer = Customer::firstOrCreate($validated);

        return (new CustomerResource($customer))->additional([
            'message' => 'Customer created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): CustomerResource
    {
        $customer->loadFromRequest();

        return (new CustomerResource($customer))->additional([
            'message' => 'Customer retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $validated = $request->validated();
        $customer->update($validated);

        return (new CustomerResource($customer))->additional([
            'message' => 'Customer updated successfully',
        ]);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer): CustomerResource
    {
        $customer->delete();

        return (new CustomerResource(null))->additional([
            'message' => 'Customer deleted successfully',
        ]);
    }
}
