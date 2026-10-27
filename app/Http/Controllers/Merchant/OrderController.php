<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderAcceptedNotification;
use App\Services\FcmService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of orders belonging to merchant restaurants.
     */
    public function index(Request $request)
    {
        $merchantId = auth()->id();

        $query = Order::with([
                'customer',
                'restaurant',
                'items.product'
            ])
            ->whereHas('restaurant', function ($q) use ($merchantId) {
                $q->where('user_id', $merchantId);
            });

        // Optional status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $orders,
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show($id)
    {
        $merchantId = auth()->id();

        $order = Order::with([
                'customer',
                'restaurant',
                'items.product'
            ])
            ->whereHas('restaurant', function ($q) use ($merchantId) {
                $q->where('user_id', $merchantId);
            })
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Order details retrieved successfully',
            'data' => $order,
        ]);
    }
     /**
     * Update order status.
     */
    public function updateStatus(Request $request, $id, FcmService $fcm)
    {
        $merchantId = auth()->id();

        $request->validate([
            'status' => 'required|in:pending,accepted,preparing,completed,cancelled',
        ]);

        $order = Order::whereHas('restaurant', function ($q) use ($merchantId) {
                $q->where('user_id', $merchantId);
            })
            ->findOrFail($id);

        $currentStatus = $order->status;
        $newStatus = $request->status;

        /*
         Allowed transitions:

         pending
            ├─> accepted
            └─> cancelled

         accepted
            └─> preparing

         preparing
            └─> completed
        */

        $allowedTransitions = [
            'pending' => ['accepted', 'cancelled'],
            'accepted' => ['preparing'],
            'preparing' => ['completed'],
        ];

        if (
            !isset($allowedTransitions[$currentStatus]) ||
            !in_array($newStatus, $allowedTransitions[$currentStatus])
        ) {
            return response()->json([
                'success' => false,
                'message' => "Cannot change status from {$currentStatus} to {$newStatus}.",
            ], 400);
        }

        $order->update([
            'status' => $newStatus,
        ]);

        if ($currentStatus === 'pending' && $newStatus === 'accepted') {
            $order->load('customer');
            $order->customer->notify(new OrderAcceptedNotification($order));
            $fcm->sendToUser(
                $order->customer,
                'Order accepted',
                "Order #{$order->id} has been accepted by the merchant.",
                ['type' => 'order_accepted', 'order_id' => $order->id, 'status' => 'accepted']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->load(
                'customer',
                'restaurant',
                'items.product'
            ),
        ]);
    }
    
}
