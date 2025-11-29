<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
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
            ])
            ->when(request()->filled('q'), function ($query) {
                $query->search(request()->q);
            })
             ->when(! in_array(request()->paginate, [false, 'false', 0, '0', 'no'], true), function ($query) {
                $perPage = ! is_numeric(request()->per_page) ? 15 : max(intval(request()->per_page), 1);

                return $query->paginate($perPage)
                    ->appends(request()->query());
            }, function ($query) {
                return $query->get();
            });

        return RoleResource::collection($roles)->additional([
            'status' => 'success',
            'message' => 'Roles retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        $validated = $request->validated();

        $role = Role::create($validated);

        $role->loadFromRequest();

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
        $role->loadFromRequest();

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

        $role->loadFromRequest();

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

        return (new RoleResource(null))->additional([
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Sync permissions to the role.
     */
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role)
    {
        $validated = $request->validated();

        $role->syncPermissions($validated['permissions']);

        $role->loadFromRequest();

        $role->load('permissions');

        $role_resource = (new RoleResource($role))->additional([
            'message' => 'Permissions synced successfully',
        ]);

        return $role_resource;
    }
}
