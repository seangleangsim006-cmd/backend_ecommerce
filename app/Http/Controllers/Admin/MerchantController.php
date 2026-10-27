<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    /**
     * Return merchants with their restaurant and operational totals.
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);

        $query = User::query()
            ->where('role', 'merchant')
            ->withCount('restaurant')
            ->with([
                'restaurant' => function ($restaurantQuery) {
                    $restaurantQuery
                        ->select('id', 'user_id', 'name', 'address', 'logo')
                        ->withCount([
                            'products',
                            'categories',
                            'orders',
                        ]);
                },
            ]);

        if ($request->filled('q')) {
            $search = $request->input('q');

            $query->where(function ($merchantQuery) use ($search) {
                $merchantQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('restaurant', function ($restaurantQuery) use ($search) {
                        $restaurantQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('merchant_status', $request->input('status'));
        }

        $merchants = $query->latest()->paginate($perPage);

        return response()->json([
            'message' => 'Merchants retrieved successfully',
            'data' => $merchants,
        ]);
    }

    /**
     * Return a merchant profile with all data belonging to the merchant's restaurant.
     */
    public function show($id)
    {
        $merchant = User::query()
            ->where('role', 'merchant')
            ->with([
                'restaurant' => function ($restaurantQuery) {
                    $restaurantQuery->with([
                        'categories.products',
                        'products.category',
                        'orders' => function ($orderQuery) {
                            $orderQuery->with(['items.product', 'customer'])
                                ->latest();
                        },
                    ]);
                },
            ])
            ->find($id);

        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found'], 404);
        }

        $restaurant = $merchant->restaurant;
        $orders = $restaurant ? $restaurant->orders : collect();
        $products = $restaurant ? $restaurant->products : collect();
        $categories = $restaurant ? $restaurant->categories : collect();

        // The related collections are returned in their own top-level keys below.
        // Hide them from the restaurant object to keep the profile response concise.
        if ($restaurant) {
            $restaurant->makeHidden(['categories', 'products', 'orders']);
        }

        return response()->json([
            'message' => 'Merchant profile retrieved successfully',
            'data' => [
                'merchant' => $merchant->makeHidden('restaurant'),
                'restaurant' => $restaurant,
                'categories' => $categories,
                'products' => $products,
                'orders' => $orders,
                'summary' => [
                    'categories_count' => $categories->count(),
                    'products_count' => $products->count(),
                    'available_products_count' => $products->where('is_available', true)->count(),
                    'orders_count' => $orders->count(),
                    'order_statuses' => $orders->countBy('status'),
                    'total_sales' => $orders
                        ->whereNotIn('status', ['cancelled', 'refunded'])
                        ->sum('total'),
                ],
            ],
        ]);
    }

    public function approve($id)
    {
        return $this->updateStatus($id, 'approved');
    }

    public function reject(Request $request, $id)
    {
        return $this->updateStatus($id, 'rejected', $request, true);
    }

    public function suspend(Request $request, $id)
    {
        return $this->updateStatus($id, 'suspended', $request, true);
    }

    public function reactivate($id)
    {
        return $this->updateStatus($id, 'approved');
    }

    private function updateStatus($id, string $status, ?Request $request = null, bool $reasonRequired = false)
    {
        $merchant = User::where('role', 'merchant')->find($id);

        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found'], 404);
        }

        $reason = null;
        if ($reasonRequired) {
            $validator = validator($request?->all() ?? [], [
                'reason' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $reason = $request->input('reason');
        }

        $merchant->update([
            'merchant_status' => $status,
            'merchant_status_reason' => $reason,
            'merchant_status_changed_at' => now(),
        ]);

        return response()->json([
            'message' => "Merchant {$status} successfully",
            'data' => $merchant->fresh(),
        ]);
    }
}
