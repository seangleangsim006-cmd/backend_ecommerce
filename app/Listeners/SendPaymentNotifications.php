<?php

namespace App\Listeners;

use App\Events\PaymentPaid;
use App\Notifications\NewOrderNotification;
use App\Services\FcmService;

class SendPaymentNotifications
{
    public function __construct(private readonly FcmService $fcm)
    {
    }

    public function handle(PaymentPaid $event): void
    {
        $order = $event->payment->order()->with('restaurant.user', 'customer')->first();

        if (!$order) {
            return;
        }
        $title = 'New order received';
        $body = "Order #{$order->id} has been paid and is waiting for acceptance.";
        $data = ['type' => 'new_order', 'order_id' => $order->id, 'status' => $order->status];

        foreach (\App\Models\User::where('role', 'admin')->get() as $admin) {
            $admin->notify(new NewOrderNotification($order));
            $this->fcm->sendToUser($admin, $title, $body, $data);
        }
    }
}