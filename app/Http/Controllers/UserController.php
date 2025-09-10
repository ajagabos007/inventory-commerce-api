<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncUserDirectPermisssionsRequest;
use App\Http\Requests\SyncUserRolesRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\AccountDeactivated;
use App\Notifications\AccountReactivated;
use App\Notifications\AdminMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

use function Illuminate\Support\defer;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $users = QueryBuilder::for(User::forRequestStores())
            ->defaultSort('-created_at')
            ->allowedSorts(
                'first_name',
                'last_name',
                'middle_name',
                'phone_number',
                'email',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes([
                'staff.store',
                'permissions',
                'roles.permissions',
            ])
            ->allowedFilters([
                'first_name',
                'last_name',
                'middle_name',
                'phone_number',
                'email',
                AllowedFilter::scope('created_after'),
                AllowedFilter::scope('permission'),
                AllowedFilter::scope('without_permission'),
                AllowedFilter::scope('role'),
                AllowedFilter::scope('without_role'),
                AllowedFilter::scope('deactivated'),
                AllowedFilter::scope('created_before'),
            ]);

        if (request()->has('q')) {
            $users->where(function ($query) {
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
            if (! is_numeric($perPage)) {
                $perPage = 15;
            } else {
                $perPage = intval($perPage);
                $perPage = $perPage >= 1 ? $perPage : 15;
            }

            $users = $users->paginate($perPage)
                ->appends(request()->query());

        } else {
            $users = $users->get();
        }

        $users_collection = UserResource::collection($users)->additional([
            'message' => 'User retrieved successfully',
        ]);

        return $users_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->loadFromRequest();

        $user_resource = (new UserResource($user))->additional([
            'message' => 'User retreived successfully',
        ]);

        return $user_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }

    /**
     * Get logged in user profile
     *
     * @method GET /api/user/profile
     *
     * @return \App\Http\Resources\UserResource
     */
    public function profile(Request $request)
    {

        /**
         * @disregard suppress Undefined method 'user'.intelephense(P1013)
         */
        $user = auth()->user();
        if (! is_null($user)) {
            $user->loadFromRequest();
        }

        $user_resource = (new UserResource($user))->additional([
            'message' => 'Profile retreived successfully',
        ]);

        return $user_resource;
    }

    /**
     * Update auth user profile
     *
     * @method POST api/user/profile
     *
     * @return \App\Http\Resources\UserResource
     */
    public function updateProfile(UpdateUserRequest $request)
    {
        $validated = $request->validated();

        if (! is_null($user = auth()->user())) {
            $user->update($validated);

            if (array_key_exists('profile_photo', $validated)) {
                $user->updateProfilePhoto($validated['profile_photo']);
            }

            $user->append('profile_photo_url');
        }

        $user_resource = (new UserResource($user))->additional([
            'message' => 'Profile updated successfully',
        ]);

        return $user_resource;
    }

    /**
     * @method POST /api/admin/user/{user}/notifications
     *
     * deactivate the specified resource.
     */
    public function sendMail(User $user, Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:191',
            'body' => 'required|string|max:2000',
        ]);

        $user->notify(new AdminMail($validated['title'], $validated['body']));

        $user_resource = (new UserResource(null))->additional([
            'message' => 'User mail successfully',
        ]);

        return $user_resource;
    }

    /**
     * @method POST /api/admin/user/{user}/deactivate
     *
     * deactivate the specified resource.
     */
    public function deactivate(User $user)
    {
        if (is_null($user->deactivated_at)) {
            $user->deactivated_at = now();
            $user->save();

            // Delete all active sessions for the user
            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            // Delete all API tokens for the user (Laravel Sanctum)
            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')->where('tokenable_id', $user->id)
                    ->where('tokenable_type', get_class($user))
                    ->delete();
            }

            defer(function () use ($user) {
                $user->notify(new AccountDeactivated);
            });
        }
        $user->deactivated_at = now();
        $user_resource = (new UserResource($user))->additional([
            'message' => 'User account deactivated successfully',
        ]);

        return $user_resource;
    }

    /**
     * @method POST /api/admin/user/{user}/activate
     *
     * deactivate the specified resource.
     */
    public function reactivate(User $user)
    {
        if (! is_null($user->deactivated_at)) {
            $user->deactivated_at = null;
            $user->save();

            defer(function () use ($user) {
                $user->notify(new AccountReactivated);
            });
        }

        $user_resource = (new UserResource($user))->additional([
            'message' => 'User account activated successfully',
        ]);

        return $user_resource;
    }

    /**
     * Sync user roles
     *
     * @method POST /api/admin/users/{user}/sync-roles
     *
     * @return \App\Http\Resources\UserResource
     */
    public function syncRoles(User $user, SyncUserRolesRequest $request)
    {
        $validated = $request->validated();
        $user->syncRoles($validated['roles']);

        $user->load('roles');
        $user->loadFromRequest();

        $user_resource = (new UserResource($user))->additional([
            'message' => 'User roles synced successfully',
        ]);

        return $user_resource;
    }

    /**
     * Sync user direct permissions
     *
     * @method POST /api/admin/users/{user}/sync-direct-permissions
     *
     * @return \App\Http\Resources\UserResource
     */
    public function syncPermissions(User $user, SyncUserDirectPermisssionsRequest $request)
    {
        $validated = $request->validated();

        $user->syncPermissions($validated['permissions']);
        $user->load('permissions');

        $user->loadFromRequest();

        $user_resource = (new UserResource($user))->additional([
            'message' => 'User direct permissions synced successfully',
        ]);

        return $user_resource;
    }
}
