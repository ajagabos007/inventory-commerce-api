<?php

namespace App\Policies;

use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentGatewayPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if(in_array($ability, ['create', 'delete','restore', 'forceDelete'])) {
            return null;
        }

        return $user->hasAnyRole('admin') ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        return Response::allow();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PaymentGateway $paymentGateway): Response
    {
        return $user->hasPermissionTo('payment_gateways.view')
            ? Response::allow() : Response::deny('You do not have permission to view payment gateway details.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PaymentGateway $paymentGateway): Response
    {
        return $user->hasPermissionTo('payment_gateways.update')
            ? Response::allow() : Response::deny('You do not have permission to update payment gateways.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PaymentGateway $paymentGateway): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PaymentGateway $paymentGateway): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PaymentGateway $paymentGateway): bool
    {
        return false;
    }
}
