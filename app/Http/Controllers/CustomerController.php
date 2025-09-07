<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
     *
     * @method GET|HEAD /api/customers
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $per_page = request()->has('per_page') ? request()->per_page : 15;

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

        if (request()->has('q')) {
            $customers->where(function ($query) {
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
            })
                ->orWhereHas('user', function ($query) {
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

            $customers = $customers->paginate($per_page)
                ->appends(request()->query());

        } else {
            $customers = $customers->get();
        }

        $customers_collection = CustomerResource::collection($customers)->additional([
            'status' => 'success',
            'message' => 'Customers retrieved successfully',
        ]);

        return $customers_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        $validated = $request->validated();

        try {

            DB::beginTransaction();

            $customer = Customer::where([
                'email' => $validated['email'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
            ])->first();

            if (! $customer) {
                $customer = Customer::create($validated);
            }

            DB::commit();

            $customer_resource = (new CustomerResource($customer))->additional([
                'message' => 'Customer created successfully',
            ]);

            return $customer_resource;

        } catch (\Throwable $th) {

            DB::rollBack();

            Log::error($th);

            return response()->json([
                'error' => $th->getMessage(),
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $customer->applyRequestIncludesAndAppends();

        $customer_resource = (new CustomerResource($customer))->additional([
            'message' => 'Customer retrieved successfully',
        ]);

        return $customer_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $validated = $request->validated();

        try {

            DB::beginTransaction();

            $customer->update($validated);

            $customer_resource = (new CustomerResource($customer))->additional([
                'message' => 'Customer updated successfully',
            ]);

            DB::commit();

            return $customer_resource;

        } catch (\Throwable $th) {

            DB::rollBack();

            Log::error($th);

            return response()->json([
                'error' => $th->getMessage(),
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        $customer_resource = (new CustomerResource(null))->additional([
            'message' => 'Customer deleted successfully',
        ]);

        return $customer_resource;
    }
}
