<?php

namespace App\Http\Controllers;

use App\Http\Resources\Reports\CustomerReportResource;
use App\Http\Resources\Reports\InventoryReportResource;
use App\Http\Resources\Reports\OrdersReportResource;
use App\Http\Resources\Reports\ProductPerformanceReportResource;
use App\Http\Resources\Reports\ProfitAnalysisResource;
use App\Http\Resources\Reports\RevenueAnalyticsResource;
use App\Http\Resources\Reports\SalesReportResource;
use App\Http\Resources\Reports\StaffPerformanceReportResource;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Staff;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;


class ReportController extends Controller
{
    /**
     * Sales Report
     *
     * @method GET /api/reports/sales
     */
    public function sales(Request $request)
    {
        // Authorization check
        // Authorization check
        Gate::authorize('viewSales', Report::class);

        $groupBy = $request->input('filter.group_by', null);

        $salesQuery = QueryBuilder::for(Sale::class)
            ->with(['cashier.user', 'saleInventories.inventory.productVariant.product'])
            ->allowedFilters([
                AllowedFilter::exact('payment_method'),
                AllowedFilter::exact('cashier_staff_id'),
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->where('created_at', '<=', $value);
                }),
                AllowedFilter::callback('group_by', function ($query, $value) {
                    // This is just a marker, actual grouping happens below
                }),
            ]);

        // Calculate aggregates
        $totalSales = $salesQuery->clone()->count();
        $totalRevenue = $salesQuery->clone()->sum('total_price');
        $averageOrderValue = $totalSales > 0 ? $totalRevenue / $totalSales : 0;
        $totalDiscount = $salesQuery->clone()->sum('discount_amount');

        // Group by period if requested
        $salesByPeriod = [];
        if ($groupBy) {
            $dateFormat = match ($groupBy) {
                'day' => '%Y-%m-%d',
                'week' => '%Y-%u',
                'month' => '%Y-%m',
                'year' => '%Y',
                default => '%Y-%m',
            };

            $salesByPeriod = $salesQuery->clone()
                ->select(
                    DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(total_price) as revenue'),
                    DB::raw('AVG(total_price) as average_value')
                )
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->map(function ($item) {
                    return [
                        'period' => $item->period,
                        'count' => (int) $item->count,
                        'revenue' => (float) $item->revenue,
                        'average_value' => (float) $item->average_value,
                    ];
                });
        }

        // Payment method breakdown
        $paymentMethodBreakdown = $salesQuery->clone()
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_price) as revenue'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'payment_method' => $item->payment_method,
                    'count' => (int) $item->count,
                    'revenue' => (float) $item->revenue,
                ];
            });

        // Recent sales
        $recentSales = $salesQuery->clone()
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Sales report retrieved successfully',
            'data' => [
                'summary' => [
                    'total_sales' => $totalSales,
                    'total_revenue' => (float) $totalRevenue,
                    'average_order_value' => (float) $averageOrderValue,
                    'total_discount' => (float) $totalDiscount,
                ],
                'sales_by_period' => $salesByPeriod,
                'payment_method_breakdown' => $paymentMethodBreakdown,
                'recent_sales' => SalesReportResource::collection($recentSales),
            ],
        ]);
    }

    /**
     * Orders Report
     *
     * @method GET /api/reports/orders
     */
    public function orders(Request $request)
    {
        // Authorization check
        Gate::authorize('viewOrders', Report::class);

        $ordersQuery = QueryBuilder::for(Order::class)
            ->with(['user', 'store', 'items'])
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('delivery_method'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->where('created_at', '<=', $value);
                }),
            ]);


        // Calculate aggregates
        $totalOrders = $ordersQuery->clone()->count();
        $totalRevenue = $ordersQuery->clone()->sum('total_price');
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Status breakdown
        $statusBreakdown = $ordersQuery->clone()
            ->setEagerLoads([])
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_price) as revenue'))
            ->groupBy('status')
            ->get();


        // Delivery method breakdown
        $deliveryMethodBreakdown = $ordersQuery->clone()
            ->setEagerLoads([])
            ->select('delivery_method', DB::raw('COUNT(*) as count'))
            ->groupBy('delivery_method')
            ->get();

        // Recent orders
        $recentOrders = $ordersQuery->clone()
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Orders report retrieved successfully',
            'data' => [
                'summary' => [
                    'total_orders' => $totalOrders,
                    'total_revenue' => (float) $totalRevenue,
                    'average_order_value' => (float) $averageOrderValue,
                ],
                'status_breakdown' => $statusBreakdown,
                'delivery_method_breakdown' => $deliveryMethodBreakdown,
                'recent_orders' => OrdersReportResource::collection($recentOrders),
            ],
        ]);
    }

    /**
     * Staff Performance Report
     *
     * @method GET /api/reports/staff-performance
     */
    public function staffPerformance(Request $request)
    {
        // Authorization check
        // Authorization check
        Gate::authorize('viewStaffPerformance', Report::class);

        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $staffId = $request->input('filter.staff_id');

        $staffQuery = QueryBuilder::for(Staff::class)
            ->with(['user', 'store'])
            ->allowedFilters([
                AllowedFilter::exact('id', 'staff_id'),
                AllowedFilter::callback('start_date', function ($query, $value) {
                    // Date filtering happens in the sales query below
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    // Date filtering happens in the sales query below
                }),
            ]);

        // Get staff with their sales performance
        $staffPerformance = $staffQuery->get()->map(function ($staff) use ($startDate, $endDate) {
            $salesQuery = $staff->sales();

            if ($startDate) {
                $salesQuery->where('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $salesQuery->where('created_at', '<=', $endDate);
            }

            $salesCount = $salesQuery->clone()->count();
            $totalRevenue = $salesQuery->clone()->sum('total_price');
            $averageTransactionValue = $salesCount > 0 ? $totalRevenue / $salesCount : 0;

            return [
                'staff' => [
                    'id' => $staff->id,
                    'staff_no' => $staff->staff_no,
                    'name' => $staff->user?->first_name . ' ' . $staff->user?->last_name,
                    'email' => $staff->user?->email,
                    'store' => $staff->store?->name,
                ],
                'performance' => [
                    'sales_count' => $salesCount,
                    'total_revenue' => (float) $totalRevenue,
                    'average_transaction_value' => (float) $averageTransactionValue,
                ],
            ];
        })->sortByDesc('performance.total_revenue')->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Staff performance report retrieved successfully',
            'data' => [
                'staff_performance' => $staffPerformance,
            ],
        ]);
    }

    /**
     * Customer Report
     *
     * @method GET /api/reports/customers
     */
    public function customers(Request $request)
    {
        // Authorization check
        // Authorization check
        Gate::authorize('viewCustomers', Report::class);

        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $limit = $request->input('filter.limit', 50);

        // Get customer purchase data using subqueries to avoid GROUP BY issues
        $customersQuery = QueryBuilder::for(Customer::class)
            ->withCount(['sales as purchase_count' => function ($query) use ($startDate, $endDate) {
                if ($startDate) $query->where('created_at', '>=', $startDate);
                if ($endDate) $query->where('created_at', '<=', $endDate);
            }])
            ->withSum(['sales as total_spent' => function ($query) use ($startDate, $endDate) {
                if ($startDate) $query->where('created_at', '>=', $startDate);
                if ($endDate) $query->where('created_at', '<=', $endDate);
            }], 'total_price')
            ->withAvg(['sales as average_order_value' => function ($query) use ($startDate, $endDate) {
                if ($startDate) $query->where('created_at', '>=', $startDate);
                if ($endDate) $query->where('created_at', '<=', $endDate);
            }], 'total_price')
            ->allowedFilters([
                AllowedFilter::exact('id', 'customer_id'),
                AllowedFilter::callback('start_date', function ($query, $value) {
                    // Handled in subqueries
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    // Handled in subqueries
                }),
                AllowedFilter::callback('limit', function ($query, $value) {
                    // Handled below
                }),
            ]);

        $customers = $customersQuery
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Customer report retrieved successfully',
            'data' => [
                'customers' => CustomerReportResource::collection($customers),
            ],
        ]);
    }

    /**
     * Inventory Report
     *
     * @method GET /api/reports/inventory
     */
    public function inventory(Request $request)
    {
        // Authorization check
        // Authorization check
        Gate::authorize('viewInventory', Report::class);

        $threshold = $request->input('filter.low_stock', 5);

        $inventoryQuery = QueryBuilder::for(Inventory::class)
            ->with([
                'productVariant.image',
                'productVariant.product.image',
                'store'
            ])
            ->allowedFilters([
                AllowedFilter::callback('product_id', function ($query, $value) {
                    $query->whereHas('productVariant', function ($q) use ($value) {
                        $q->where('product_id', $value);
                    });
                }),
                AllowedFilter::scope('low_stock', 'lowStock'),
                AllowedFilter::scope('out_of_stock', 'outOfStock'),

            ]);


        // Get all inventory items
        $inventoryItems = $inventoryQuery->get();

        // Calculate summary
        $totalItems = Inventory::count();
        $lowStockItems = Inventory::lowStock($threshold)->count();
        $outOfStockItems = Inventory::outOfStock(true)->count();
        $totalStockValue = Inventory::with(['productVariant.image','productVariant.product.image'])
            ->get()
            ->sum(function ($inventory) {
                return $inventory->quantity * ($inventory->productVariant?->cost_price ?? 0);
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Inventory report retrieved successfully',
            'data' => [
                'summary' => [
                    'total_items' => $totalItems,
                    'low_stock_items' => $lowStockItems,
                    'out_of_stock_items' => $outOfStockItems,
                    'total_stock_value' => (float) $totalStockValue,
                ],
                'inventory_items' => InventoryReportResource::collection($inventoryItems),
            ],
        ]);
    }

    /**
     * Product Performance Report
     *
     * @method GET /api/reports/product-performance
     */
    public function productPerformance(Request $request)
    {
        // Authorization check
        // Authorization check
        Gate::authorize('viewProductPerformance', Report::class);

        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $limit = $request->input('filter.limit', 10);
        $type = $request->input('filter.type', 'top_selling');

        QueryBuilder::for(Product::class)
            ->allowedFilters([
                AllowedFilter::callback('start_date', function ($query, $value) {
                    // Used below
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    // Used below
                }),
                AllowedFilter::callback('limit', function ($query, $value) {
                    // Used below
                }),
                AllowedFilter::callback('type', function ($query, $value) {
                    // Used below
                }),
            ])
            ->with('image');

        if ($type === 'trending' && $startDate && $endDate) {
            // Calculate days between dates
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);
            $days = $start->diffInDays($end);

            $products = Product::trending($days)->limit($limit)->get();
        } elseif ($startDate && $endDate) {
            $products = Product::popularInPeriod(
                $startDate,
                $endDate,
                'performance_period'
            )
                ->with('image')
            ->limit($limit)->get();
        } else {
            $products = Product::with('image')->topSelling($limit)->get();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Product performance report retrieved successfully',
            'data' => [
                'products' => ProductPerformanceReportResource::collection($products),
            ],
        ]);
    }

    /**
     * Revenue Analytics Report
     *
     * @method GET /api/reports/revenue-analytics
     */
    public function revenueAnalytics(Request $request)
    {
        // Authorization check
        // Authorization check
        Gate::authorize('viewRevenueAnalytics', Report::class);

        $period = $request->input('filter.period', 'month');
        $dateFormat = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m',
        };

        $salesQuery = QueryBuilder::for(Sale::class)
            ->allowedFilters([
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->where('created_at', '<=', $value);
                }),
                AllowedFilter::callback('period', function ($query, $value) {
                    // Period is used for grouping below
                }),
            ]);

        // Revenue by period
        $revenueByPeriod = $salesQuery->clone()
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(total_price) as revenue'),
                DB::raw('SUM(subtotal_price) as subtotal'),
                DB::raw('SUM(tax) as tax'),
                DB::raw('SUM(discount_amount) as discount')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Revenue by payment method
        $revenueByPaymentMethod = $salesQuery->clone()
            ->select('payment_method', DB::raw('SUM(total_price) as revenue'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        // Calculate growth rate (comparing first and last period)
        $growthRate = 0;
        if ($revenueByPeriod->count() >= 2) {
            $firstRevenue = $revenueByPeriod->first()->revenue;
            $lastRevenue = $revenueByPeriod->last()->revenue;
            if ($firstRevenue > 0) {
                $growthRate = (($lastRevenue - $firstRevenue) / $firstRevenue) * 100;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Revenue analytics report retrieved successfully',
            'data' => [
                'revenue_by_period' => $revenueByPeriod,
                'revenue_by_payment_method' => $revenueByPaymentMethod,
                'growth_rate' => round($growthRate, 2),
            ],
        ]);
    }

    /**
     * Profit Analysis Report
     *
     * @method GET /api/reports/profit-analysis
     */
    public function profitAnalysis(Request $request)
    {
        // Authorization check
        // Authorization check
        Gate::authorize('viewProfitAnalysis', Report::class);

        $productId = $request->input('filter.product_id');

        $salesQuery = QueryBuilder::for(Sale::class)
            ->with(['saleInventories.inventory.productVariant.product'])
            ->allowedFilters([
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->where('created_at', '<=', $value);
                }),
                AllowedFilter::callback('product_id', function ($query, $value) {
                    // Product filtering happens in the loop below
                }),
            ]);

        $sales = $salesQuery->get();

        $totalRevenue = 0;
        $totalCost = 0;
        $productProfits = [];

        foreach ($sales as $sale) {
            foreach ($sale->saleInventories as $saleInventory) {
                $productVariant = $saleInventory->inventory?->productVariant;
                $product = $productVariant?->product;

                if (!$product) {
                    continue;
                }

                // Skip if filtering by product and this is not the product
                if ($productId && $product->id !== $productId) {
                    continue;
                }

                $revenue = $saleInventory->total_price;
                $cost = $saleInventory->quantity * ($productVariant->cost_price ?? 0);

                $totalRevenue += $revenue;
                $totalCost += $cost;

                $productIdKey = $product->id;
                if (!isset($productProfits[$productIdKey])) {
                    $productProfits[$productIdKey] = [
                        'product' => [
                            'id' => $product->id,
                            'name' => $product->name,
                        ],
                        'revenue' => 0,
                        'cost' => 0,
                        'profit' => 0,
                        'margin' => 0,
                        'quantity_sold' => 0,
                    ];
                }

                $productProfits[$productIdKey]['revenue'] += $revenue;
                $productProfits[$productIdKey]['cost'] += $cost;
                $productProfits[$productIdKey]['quantity_sold'] += $saleInventory->quantity;
            }
        }

        // Calculate profit and margin for each product
        foreach ($productProfits as &$productProfit) {
            $productProfit['profit'] = $productProfit['revenue'] - $productProfit['cost'];
            $productProfit['margin'] = $productProfit['revenue'] > 0
                ? ($productProfit['profit'] / $productProfit['revenue']) * 100
                : 0;
            $productProfit['margin'] = round($productProfit['margin'], 2);
        }

        // Sort by profit descending
        $productProfits = collect($productProfits)->sortByDesc('profit')->values();

        $totalProfit = $totalRevenue - $totalCost;
        $overallMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return response()->json([
            'status' => 'success',
            'message' => 'Profit analysis report retrieved successfully',
            'data' => [
                'summary' => [
                    'total_revenue' => (float) $totalRevenue,
                    'total_cost' => (float) $totalCost,
                    'total_profit' => (float) $totalProfit,
                    'overall_margin' => round($overallMargin, 2),
                ],
                'product_profits' => $productProfits,
            ],
        ]);
    }
}
