<?php

namespace App\Listeners;

use App\Events\OrderUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogOrderUpdate implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderUpdated $event): void
    {
        $order = $event->order;
        
        Log::info('Order updated', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount' => $order->amount,
            'status' => $order->status->value,
        ]);

        // Future: Audit trail implementation
        // Future: Notify relevant parties of changes
    }
}