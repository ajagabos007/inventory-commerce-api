<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use App\Models\User;
use App\QueryBuilder\Filters\StaffIsManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StaffController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Staff::class, 'staff');
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

        $user = auth()->user();

        $staffQ = Staff::when(! $user || ! $user->is_admin, function ($query) {
            $query->forCurrentStore();
        });

        $staffs = QueryBuilder::for($staffQ)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'staff_no',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'user.id',
                AllowedFilter::custom('is_store_manager', new StaffIsManager),
            ])
            ->allowedIncludes([
                'user',
                'user.roles',
                'managedStore',
                'store',
            ]);
        if (request()->has('q')) {
            $searchTerm = '%'.request()->q.'%';

            $staffs->where(function ($query) use ($searchTerm) {
                $model = $query->getModel();
                $table = $model->getTable();

                $cacheKey = "{$table}_column_listing";
                $columns = Cache::rememberForever($cacheKey, function () use ($table) {
                    return Schema::getColumnListing($table);
                });

                foreach ($columns as $index => $column) {
                    $command = $index == 0 ? 'where' : 'orWhere';
                    $query->{$command}($column, 'like', $searchTerm);
                }
            })
                ->orWhereHas('user', function ($query) use ($searchTerm) {
                    $model = $query->getModel();
                    $table = $model->getTable();

                    $cacheKey = "{$table}_column_listing";
                    $columns = Cache::rememberForever($cacheKey, function () use ($table) {
                        return Schema::getColumnListing($table);
                    });

                    foreach ($columns as $index => $column) {
                        $command = $index == 0 ? 'where' : 'orWhere';
                        $query->{$command}($column, 'like', $searchTerm);
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
            if (! is_numeric($perPage)) {
                $perPage = 15;
            } else {
                $perPage = intval($perPage);
                $perPage = $perPage >= 1 ? $perPage : 15;
            }

            $staffs = $staffs->paginate($perPage)
                ->appends(request()->query());

        } else {
            $staffs = $staffs->get();
        }

        $staffs_collection = StaffResource::collection($staffs)->additional([
            'status' => 'success',
            'message' => 'Staffs retrieved successfully',
        ]);

        return $staffs_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStaffRequest $request)
    {
        $validated = $request->validated();

        try {

            DB::beginTransaction();

            $password = 'password';
            $validated['password'] = Hash::make($password);

            $user = User::create($validated);
            $staff = $user->staff()->create($validated);
            $staff->load(['user']);

            if (array_key_exists('role_id', $validated)) {

                $staff->user->syncRoles([$validated['role_id']]);

            }

            $staff_resource = (new StaffResource($staff))->additional([
                'message' => 'Staff created successfully',
            ]);

            $user->notify(new \App\Notifications\Auth\PasswordCreated($user, $password));

            DB::commit();

            return $staff_resource;

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
    public function show(Staff $staff)
    {
        $staff->loadFromRequest();

        $staff_resource = (new StaffResource($staff))->additional([
            'message' => 'Staff retrieved successfully',
        ]);

        return $staff_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStaffRequest $request, Staff $staff)
    {
        $validated = $request->validated();

        try {

            DB::beginTransaction();

            $staff->user->update($validated);
            $staff->update($validated);

            if (array_key_exists('role_id', $validated)) {

                $staff->user->syncRoles([$validated['role_id']]);

            }

            $staff_resource = (new StaffResource($staff))->additional([
                'message' => 'Staff updated successfully',
            ]);

            DB::commit();

            return $staff_resource;

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
    public function destroy(Staff $staff)
    {
        $staff->delete();

        $staff_resource = (new StaffResource(null))->additional([
            'message' => 'Staff deleted successfully',
        ]);

        return $staff_resource;
    }
}
