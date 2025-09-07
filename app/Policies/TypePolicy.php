<?php

namespace App\Policies;

use App\Models\Type;
use App\Models\User;

class TypePolicy
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
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Type $type): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission('can create type');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Type $type): bool
    {
        return $user->hasAnyPermission('can update type');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Type $type): bool
    {
        return $user->hasAnyPermission('can delete type');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Type $type): bool
    {
        return $user->hasAnyPermission('can restore type');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Type $type): bool
    {
        return $user->hasAnyPermission('can force delete type');
    }
}
