<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

class PermissionController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Permission::class, 'permission');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/permissions
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $per_page = request()->has('per_page') ? request()->per_page : 15;

        $permissions = QueryBuilder::for(Permission::class)
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
                'users',
                'roles',
                'roles.permissions',
                //    AllowedInclude::count('usersCount', 'users'),
            ]);

        if (request()->has('q')) {
            $permissions->where(function ($query) {
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

            $permissions = $permissions->paginate($per_page)
                ->appends(request()->query());

        } else {
            $permissions = $permissions->get();
        }

        $permissions_collection = PermissionResource::collection($permissions)->additional([
            'status' => 'success',
            'message' => 'Permissions retrieved successfully',
        ]);

        return $permissions_collection;
    }

    /**
     * Display the specified resource.
     */
    public function show(Permission $permission)
    {
        $permission->applyRequestIncludesAndAppends();

        $permission_resource = (new PermissionResource($permission))->additional([
            'message' => 'Permission retrieved successfully',
        ]);

        return $permission_resource;
    }
}
