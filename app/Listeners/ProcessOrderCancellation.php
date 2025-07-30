<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessOrderCancellation implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;
        
        Log::info('Order cancelled', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount' => $order->amount,
            'type' => $order->order_type->value,
        ]);

        // Future: Reverse any pending transactions
        // Future: Release any held funds
        // Future: Send cancellation notifications
    }
}