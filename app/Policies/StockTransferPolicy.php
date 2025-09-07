<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockTransferPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?Response
    {
        if (($ability == 'dispatch')) {
            return null; // Skip pre-authorization for dispatch action: to be managed in the dispatch method
        }

        // Allow all admin users to perform any action
        if ($user->hasAnyRole('admin')) {
            return Response::allow();
        }

        // Deny all other users if they are not staff
        if (! $user->isStaff) {
            return Response::deny('You must be a staff member to perform this action.');
        }

        return null;
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
    public function view(User $user, StockTransfer $stockTransfer): Response
    {
        if ($user->hasPermissionTo('stock_transfer.view') || $user->hasPermissionTo('stock_transfer.receive')) {
            return Response::allow();

        }

        return Response::deny('You do not have permission to view this stock transfer.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        if (! $user->hasPermissionTo('stock_transfer.create')) {
            return Response::deny('You do not have permission to create stock transfers.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockTransfer $stockTransfer): Response
    {
        if ($stockTransfer->sender_id !== $user->id) {
            return Response::deny('Update not allowed: You can only update stock transfers you created.');
        }

        if ($stockTransfer->from_store_id !== $user->staff->store_id) {
            return Response::deny('Update not allowed: You can only update stock transfers from your own store.');
        }

        if (! empty($stockTransfer->dispatched_at)) {
            return Response::deny('Update not allowed: This stock transfer has already been dispatched.');
        }
        if (! empty($stockTransfer->accepted_at)) {
            return Response::deny('Update not allowed: This stock transfer has already been accepted.');
        }
        if (! empty($stockTransfer->rejected_at)) {
            return Response::deny('Update not allowed: This stock transfer has already been rejected.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockTransfer $stockTransfer): Response
    {
        if (! empty($stockTransfer->dispatched_at)) {
            return Response::deny('Delete not allowed: This stock transfer has already been dispatched.');
        }
        if (! empty($stockTransfer->accepted_at)) {
            return Response::deny('Delete not allowed: This stock transfer has already been accepted.');
        }
        if (! empty($stockTransfer->rejected_at)) {
            return Response::deny('Delete not allowed: This stock transfer has already been rejected.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StockTransfer $stockTransfer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StockTransfer $stockTransfer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can  dispatch the model.
     */
    public function dispatch(User $user, StockTransfer $stockTransfer): Response
    {
        if ($stockTransfer->inventories()->count() == 0) {
            return Response::deny('You cannot dispatch a stock transfer with no products.');
        }

        // Allow all admin users to perform any action
        if ($user->hasAnyRole('admin')) {
            return Response::allow();
        }

        if (! empty($stockTransfer->dispatched_at)) {
            return Response::deny('You cannot dispatch a stock transfer that has already been dispatched.');
        }
        if (! empty($stockTransfer->accepted_at)) {
            return Response::deny('You cannot dispatch a stock transfer that has already been accepted.');
        }
        if (! empty($stockTransfer->rejected_at)) {
            return Response::deny('You cannot dispatch a stock transfer that has already been rejected.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can  dispatch the model.
     */
    public function accept(User $user, StockTransfer $stockTransfer): Response
    {
        if (! $user->hasPermissionTo('stock_transfer.receive')) {
            return Response::deny('You do not have permission to accept stock transfers.');
        }

        if (empty($stockTransfer->dispatched_at)) {
            return Response::deny('Accepting failed: This stock transfer has not been dispatched yet.');
        }
        if (! empty($stockTransfer->rejected_at)) {
            return Response::deny('Accepting failed: This stock transfer has already been rejected.');
        }
        if (! empty($stockTransfer->accepted_at)) {
            return Response::deny('Accepting failed: This stock transfer has already been accepted.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can  dispatch the model.
     */
    public function reject(User $user, StockTransfer $stockTransfer): Response
    {
        if (! $user->hasPermissionTo('stock_transfer.receive')) {
            return Response::deny('You do not have permission to accept stock transfers.');
        }

        if (empty($stockTransfer->dispatched_at)) {
            return Response::deny('Rejection failed: This stock transfer has not been dispatched yet.');
        }
        if (! empty($stockTransfer->accepted_at)) {
            return Response::deny('Rejection failed: This stock transfer has already been accepted.');
        }
        if (! empty($stockTransfer->rejected_at)) {
            return Response::deny('Rejection failed: This stock transfer has already been rejected.');
        }

        return Response::allow();
    }
}
