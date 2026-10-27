<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Return the key operational metrics needed by the admin dashboard.
     */
    public function index(Request $request)
    {
        $recentLimit = min(max((int) $request->get('recent_limit', 10), 1), 50);
        $lowStockLimit = min(max((int) $request->get('low_stock_limit', 10), 1), 50);
        $lowStockThreshold = min(max((int) $request->get('low_stock_threshold', 5), 0), 1000);
        $startOfToday = now()->startOfDay();
        $endOfToday = now()->endOfDay();
        $startOfWeek = now()->startOfWeek();

        $todayOrders = Order::query()->whereBetween('created_at', [$startOfToday, $endOfToday]);

        $statusCounts = (clone $todayOrders)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $orderStatuses = [
            'pending',
            'accepted',
            'preparing',
            'delivering',
            'completed',
            'cancelled',
        ];

        $recentOrders = Order::with([
            'customer:id,name,email',
            'restaurant:id,user_id,name,address,logo',
        ])
            ->latest()
            ->limit($recentLimit)
            ->get();

        $nonCancelledOrders = Order::query()->where('status', '!=', 'cancelled');

        $lowStockProducts = Product::with([
            'restaurant:id,user_id,name',
            'category:id,restaurant_id,name',
        ])
            ->whereNotNull('stock')
            ->where('stock', '<=', $lowStockThreshold)
            ->orderBy('stock')
            ->limit($lowStockLimit)
            ->get();

        $pendingMerchants = User::where('role', 'merchant')
            ->where('merchant_status', 'pending')
            ->with('restaurant:id,user_id,name,address,logo')
            ->latest('merchant_status_changed_at')
            ->limit(10)
            ->get(['id', 'name', 'email', 'merchant_status', 'merchant_status_changed_at']);

        return response()->json([
            'message' => 'Dashboard data retrieved successfully',
            'data' => [
                'totals' => [
                    'merchants' => User::where('role', 'merchant')->count(),
                    'restaurants' => Restaurant::count(),
                    'products' => Product::count(),
                    'orders' => Order::count(),
                ],
                'merchant_statuses' => [
                    'pending' => User::where('role', 'merchant')->where('merchant_status', 'pending')->count(),
                    'approved' => User::where('role', 'merchant')->where('merchant_status', 'approved')->count(),
                    'rejected' => User::where('role', 'merchant')->where('merchant_status', 'rejected')->count(),
                    'suspended' => User::where('role', 'merchant')->where('merchant_status', 'suspended')->count(),
                ],
                'today' => [
                    'date' => $startOfToday->toDateString(),
                    'sales' => (clone $todayOrders)
                        ->where('status', '!=', 'cancelled')
                        ->sum('total'),
                    'orders_count' => $todayOrders->count(),
                    'order_status_counts' => array_replace(
                        array_fill_keys($orderStatuses, 0),
                        $statusCounts
                    ),
                ],
                'performance' => [
                    'all_time_sales' => (clone $nonCancelledOrders)->sum('total'),
                    'this_week_sales' => (clone $nonCancelledOrders)
                        ->where('created_at', '>=', $startOfWeek)
                        ->sum('total'),
                    'this_week_orders_count' => (clone $nonCancelledOrders)
                        ->where('created_at', '>=', $startOfWeek)
                        ->count(),
                    'average_order_value' => (clone $nonCancelledOrders)->avg('total'),
                ],
                'inventory' => [
                    'low_stock_threshold' => $lowStockThreshold,
                    'out_of_stock_count' => Product::whereNotNull('stock')->where('stock', '<=', 0)->count(),
                    'unavailable_products_count' => Product::where('is_available', false)->count(),
                    'low_stock_products' => $lowStockProducts,
                ],
                'pending_merchants' => $pendingMerchants,
                'recent_orders' => $recentOrders,
                'generated_at' => now(),
            ],
        ]);
    }
}
