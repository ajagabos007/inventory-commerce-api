<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StorePolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasAnyRole('admin') ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, store $store): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['store.create']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, store $store): bool
    {
        return $user->hasAnyPermission(['store.update']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, store $store): bool
    {
        return $user->hasAnyPermission(['store.delete']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, store $store): bool
    {
        return $user->hasAnyPermission(['store.restore']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, store $store): bool
    {
        return $user->hasAnyPermission(['store.force_delete']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function switch(?User $user): Response
    {
        return Response::allow();
//       return  $user->hasAnyPermission(['store.switch'])
//                ?Response::allow()
//                : Response::deny('You do not have permission to access this page.');
    }
}
