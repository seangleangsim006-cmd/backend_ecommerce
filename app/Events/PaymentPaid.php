<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentPaid implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('payment.' . $this->payment->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.paid';
    }

    public function broadcastWith(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'order_id' => $this->payment->order_id,
            'status' => 'paid',
            'amount' => (float) $this->payment->amount,
            'currency' => $this->payment->currency,
            'paid_at' => $this->payment->paid_at?->toISOString(),
        ];
    }
}
