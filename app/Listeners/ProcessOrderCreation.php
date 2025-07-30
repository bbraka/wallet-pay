<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessOrderCreation implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderCreated $event): void
    {
        $order = $event->order;
        
        Log::info('Order created', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount' => $order->amount,
            'type' => $order->order_type->value,
        ]);

        // Future: Integrate with payment processors
        // Future: Send notifications to users
        // Future: Create initial transaction records
    }
}