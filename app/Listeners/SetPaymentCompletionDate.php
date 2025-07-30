<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Enums\OrderStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SetPaymentCompletionDate implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OrderStatusChanged $event): void
    {
        // Set payment completion date when order becomes completed
        if ($event->newStatus === OrderStatus::COMPLETED && 
            $event->previousStatus !== OrderStatus::COMPLETED &&
            $event->order->payment_completion_date === null) {
            
            $event->order->update([
                'payment_completion_date' => now()
            ]);
        }
    }
}
