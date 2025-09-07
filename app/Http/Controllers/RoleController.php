<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\QueryBuilder;

class RoleController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Role::class, 'role');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/roles
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $per_page = request()->has('per_page') ? request()->per_page : 15;

        $roles = QueryBuilder::for(Role::class)
            ->defaultSort('name')
            ->allowedSorts(
                'name',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'name',
            ])
            ->allowedIncludes([
                'permissions',
            ]);

        if (request()->has('q')) {
            $roles->where(function ($query) {
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

            $roles = $roles->paginate($per_page)
                ->appends(request()->query());

        } else {
            $roles = $roles->get();
        }

        $roles_collection = RoleResource::collection($roles)->additional([
            'status' => 'success',
            'message' => 'Roles retrieved successfully',
        ]);

        return $roles_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        $validated = $request->validated();

        $role = Role::create($validated);

        $role->applyRequestIncludesAndAppends();

        $role_resource = (new RoleResource($role))->additional([
            'message' => 'Role created successfully',
        ]);

        return $role_resource;
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        $role->applyRequestIncludesAndAppends();

        $role_resource = (new RoleResource($role))->additional([
            'message' => 'Role retrieved successfully',
        ]);

        return $role_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $validated = $request->validated();
        $role->update($validated);

        $role->applyRequestIncludesAndAppends();

        $role_resource = (new RoleResource($role))->additional([
            'message' => 'Role updated successfully',
        ]);

        return $role_resource;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        $role->delete();

        $role_resource = (new RoleResource(null))->additional([
            'message' => 'Role deleted successfully',
        ]);

        return $role_resource;
    }

    /**
     * Sync permissions to the role.
     */
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role)
    {
        $validated = $request->validated();

        $role->syncPermissions($validated['permissions']);

        $role->applyRequestIncludesAndAppends();

        $role->load('permissions');

        $role_resource = (new RoleResource($role))->additional([
            'message' => 'Permissions synced successfully',
        ]);

        return $role_resource;
    }
}
