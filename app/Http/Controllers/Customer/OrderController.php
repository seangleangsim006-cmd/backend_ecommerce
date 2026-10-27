<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Notifications\NewOrderNotification;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    //
      // Place Order
    public function store(Request $request, FcmService $fcm)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {

            $total = 0;

            $order = Order::create([
                'customer_id' => auth()->id(),
                'restaurant_id' => $request->restaurant_id,
                'total' => 0,
                'status' => 'pending',
            ]);

            foreach ($request->items as $item) {

                $product = Product::findOrFail($item['product_id']);

                // Prevent ordering products from another restaurant
                if ($product->restaurant_id != $request->restaurant_id) {
                    throw new \Exception(
                        "Product {$product->name} does not belong to this restaurant."
                    );
                }

                $price = $product->price;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $price,
                ]);

                $total += $price * $item['quantity'];
            }

            $order->update([
                'total' => $total,
            ]);

            DB::commit();

            $order->load('restaurant.user');

            if ($order->restaurant?->user) {
                $merchant = $order->restaurant->user;
                $merchant->notify(new NewOrderNotification($order));
                $fcm->sendToUser(
                    $merchant,
                    'New order received',
                    "Order #{$order->id} is waiting for your acceptance.",
                    ['type' => 'new_order', 'order_id' => $order->id, 'status' => $order->status]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => $order->load(
                    'restaurant',
                    'items.product'
                ),
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    // Customer Orders
    public function index()
    {
        $orders = Order::with([
                'restaurant',
                'items.product'
            ])
            ->where('customer_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    // Customer Order History
    public function history(Request $request)
    {
        $allowedStatuses = ['completed', 'cancelled'];
        $status = $request->query('status');

        $query = Order::with([
                'restaurant',
                'items.product'
            ])
            ->where('customer_id', auth()->id())
            ->whereIn('status', $allowedStatuses);

        if ($status) {
            if (!in_array($status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status filter. Allowed values are completed or cancelled.',
                ], 422);
            }

            $query->where('status', $status);
        }

        $orders = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    // Order Details
    public function show($id)
    {
        $order = Order::with([
                'restaurant',
                'items.product'
            ])
            ->where('customer_id', auth()->id())
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    // Cancel Order
    public function cancel($id)
    {
        $order = Order::where('customer_id', auth()->id())
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if (!in_array($order->status, ['pending', 'accepted'])) {
            return response()->json([
                'success' => false,
                'message' => 'This order cannot be cancelled.',
            ], 400);
        }

        $order->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
        ]);
    }
}
