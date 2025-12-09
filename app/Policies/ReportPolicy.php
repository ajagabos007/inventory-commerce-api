<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class ReportPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewSales(User $user): bool
    {
        return $user->can(Permission::REPORTS_VIEW_SALES->value);
    }

    public function viewOrders(User $user): bool
    {
        return $user->can(Permission::REPORTS_VIEW_ORDERS->value);
    }

    public function viewStaffPerformance(User $user): bool
    {
        return $user->can(Permission::REPORTS_VIEW_STAFF->value);
    }

    public function viewCustomers(User $user): bool
    {
        return $user->can(Permission::REPORTS_VIEW_CUSTOMERS->value);
    }

    public function viewInventory(User $user): bool
    {
        return $user->can(Permission::REPORTS_VIEW_INVENTORY->value);
    }

    public function viewProductPerformance(User $user): bool
    {
        return $user->can(Permission::REPORTS_VIEW_PRODUCTS->value);
    }

    public function viewRevenueAnalytics(User $user): bool
    {
        return $user->can(Permission::REPORTS_VIEW_FINANCIAL->value);
    }

    public function viewProfitAnalysis(User $user): bool
    {
        return $user->can(Permission::REPORTS_VIEW_FINANCIAL->value);
    }
}
