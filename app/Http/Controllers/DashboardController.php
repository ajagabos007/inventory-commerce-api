<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Sale;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    /**
     * Get dashboard summary
     */
    public function summary(Request $request)
    {
        $summary = [];

        $is_admin = auth()->user()?->is_admin;

        $can_view_users = Gate::allows('viewAny', User::class);

        if ($can_view_users) {
            $userQ = User::query();
            $summary['users'] = [
                'total' => $userQ->clone()->count(),
                'active' => $userQ->clone()->active()->count(),
                'inactive' => $userQ->clone()->inactive()->count(),
                'new_signups' => $userQ->clone()->where('created_at', '>=', now()->subMonth())->count(),
            ];
        }

        $can_view_staff = Gate::allows('viewAny', Staff::class);
        $staffQ = Staff::forCurrentStore();

        if ($can_view_staff) {
            $summary['staff'] = [
                'total' => $staffQ->clone()->count(),
                'active' => $staffQ->clone()->whereHas('user', fn ($query) => $query->active())->count(),
                'active' => $staffQ->clone()->whereHas('user', fn ($query) => $query->inactive())->count(),
                'new_staff' => $staffQ->clone()->where('created_at', '>=', now()->subMonth())->count(),
            ];
        }

        $can_view_sales = Gate::allows('viewAny', Sale::class);
        if ($can_view_sales) {

            $saleQ = Sale::query();

            $summary['sales'] = [
                'total' => $saleQ->clone()->count(),
                'new_sales' => $saleQ->clone()->where('created_at', '>=', now()->subMonth())->count(),
                'total_amount' => $saleQ->clone()->sum('total_price'),
                'average_amount' => $saleQ->clone()->avg('total_price'),
            ];
        }

        $can_view_inventories = Gate::allows('viewAny', Inventory::class);
        if ($can_view_inventories) {
            $inventoryQ = Inventory::query()
                ->with([
                    'item.type',
                    'item.colour',
                    'item.category',
                ]);

            $summary['products'] = [
                'total' => $inventoryQ->clone()->count(),
                'available' => $inventoryQ->clone()->where('quantity', '>', 0)->count(),
                'low_stock' => $inventoryQ->clone()
                    ->where('quantity', '<=', 5)
                    ->where('quantity', '>', 0)
                    ->orderBy('updated_at', 'desc')
                    ->take(5)
                    ->get()
                    ->count(),

                'out_of_stock' => $inventoryQ->clone()
                    ->where('quantity', '<=', 0)
                    ->orderBy('updated_at', 'desc')
                    ->take(5)
                    ->get()
                    ->map(function ($inventory) {
                        return [
                            'item' => $inventory->item,
                            'quantity' => $inventory->quantity,
                            'last_updated' => $inventory->updated_at->diffForHumans(), ];
                    }),

                'new_products' => $inventoryQ->clone()->where('created_at', '>=', now()->subMonth())->count(),
                'top_selling_products' => $inventoryQ->clone()
                    ->withCount('sales')
                    ->orderBy('sales_count', 'desc')
                    ->take(5)
                    ->get()
                    ->map(function ($inventory) {
                        return [
                            'item' => $inventory->item,
                            'sales_count' => $inventory->sales_count,
                            'total_revenue' => $inventory->sales->sum('total_price'),
                        ];
                    }),
            ];
        }

        // Add more summary data as needed
        return response()->json([
            'data' => $summary,
            'message' => 'Dashboard summary retrieved successfully.',
            'status' => 'success',
        ]);
    }
}
