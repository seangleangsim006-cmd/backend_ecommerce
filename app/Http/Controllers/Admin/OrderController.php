<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Notifications\OrderAcceptedNotification;
use App\Services\FcmService;

class OrderController extends Controller
{
    // GET /admin/orders
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $query = Order::with(['items', 'customer', 'restaurant']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qry) use ($q) {
                $qry->where('id', $q)
                    ->orWhereHas('customer', function ($u) use ($q) {
                        $u->where('name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Orders retrieved successfully',
            'data' => $orders
        ], 200);
    }

    // GET /admin/orders/{id}
    public function show($id)
    {
        $order = Order::with(['items', 'customer', 'restaurant'])->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'message' => 'Order retrieved successfully',
            'data' => $order
        ], 200);
    }

    // PUT /admin/orders/{id}/status
    public function updateStatus(Request $request, $id, FcmService $fcm)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,accepted,preparing,delivering,completed,cancelled,refunded'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $previousStatus = $order->status;
        $order->status = $request->status;
        $order->save();

        if ($previousStatus === 'pending' && $order->status === 'accepted') {
            $order->load('customer');
            $order->customer->notify(new OrderAcceptedNotification($order));
            $fcm->sendToUser(
                $order->customer,
                'Order accepted',
                "Order #{$order->id} has been accepted by admin.",
                ['type' => 'order_accepted', 'order_id' => $order->id, 'status' => 'accepted']
            );
        }

        return response()->json(['message' => 'Order status updated', 'data' => $order], 200);
    }

    // PUT /admin/orders/{id}/assign
    public function assignRestaurant(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|exists:restaurants,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $restaurant = Restaurant::find($request->restaurant_id);
        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        $order->restaurant_id = $restaurant->id;
        $order->save();

        return response()->json(['message' => 'Order assigned to restaurant', 'data' => $order], 200);
    }

    // GET /admin/orders/export
    public function exportCsv(Request $request)
    {
        $query = Order::with(['items', 'customer', 'restaurant']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $filename = 'orders_export_' . now()->format('Ymd_His') . '.csv';

        $callback = function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'customer', 'restaurant', 'total', 'status', 'created_at']);

            foreach ($orders as $order) {
                fputcsv($out, [
                    $order->id,
                    optional($order->customer)->email ?? null,
                    optional($order->restaurant)->name ?? null,
                    $order->total ?? null,
                    $order->status,
                    $order->created_at->toDateTimeString()
                ]);
            }

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv'
        ]);
    }

    // GET /admin/restaurants/{id}/orders
    // Optional query: group_by_customer=1 to group orders by customer
    public function restaurantOrders(Request $request, $restaurantId)
    {
        $restaurant = Restaurant::find($restaurantId);
        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        $query = Order::with(['items', 'customer'])->where('restaurant_id', $restaurant->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        if ($request->boolean('group_by_customer', false)) {
            $grouped = $orders->groupBy('customer_id')->map(function ($orders, $customerId) {
                $customer = $orders->first()->customer;
                return [
                    'customer' => $customer,
                    'orders' => $orders->values(),
                ];
            })->values();

            return response()->json([
                'message' => 'Orders grouped by customer retrieved',
                'data' => $grouped
            ], 200);
        }

        return response()->json([
            'message' => 'Restaurant orders retrieved successfully',
            'data' => $orders
        ], 200);
    }
}
