<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Transaction;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\TransactionType;
use App\Enums\TransactionStatus;
use App\Services\Merchant\OrdersService;
use App\Events\OrderStatusChanged;
use App\Events\OrderCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private OrdersService $ordersService;
    private User $sender;
    private User $receiver;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->ordersService = new OrdersService();
        
        $this->sender = User::factory()->create([
            'name' => 'John Sender',
            'email' => 'sender@example.com',
            'wallet_amount' => 1000.00
        ]);
        
        $this->receiver = User::factory()->create([
            'name' => 'Jane Receiver', 
            'email' => 'receiver@example.com',
            'wallet_amount' => 500.00
        ]);
        
        // Create initial transactions to properly set up the wallet balances
        Transaction::create([
            'user_id' => $this->sender->id,
            'type' => TransactionType::CREDIT,
            'amount' => 1000.00,
            'status' => TransactionStatus::ACTIVE,
            'description' => 'Initial balance for sender',
            'created_by' => $this->sender->id,
        ]);
        
        Transaction::create([
            'user_id' => $this->receiver->id,
            'type' => TransactionType::CREDIT,
            'amount' => 500.00,
            'status' => TransactionStatus::ACTIVE,
            'description' => 'Initial balance for receiver',
            'created_by' => $this->receiver->id,
        ]);
    }

    public function test_confirm_pending_transfer_updates_status_and_balances()
    {
        // Arrange: Set up comprehensive testing environment
        Event::spy();
        Log::spy(); // Also spy on logging to capture observer log calls
        
        // Arrange: Create a pending transfer using the proper service
        // This ensures money is withdrawn from sender immediately
        $orderService = app(\App\Services\OrderService::class);
        $order = $orderService->createInternalTransferOrder(
            $this->sender,
            $this->receiver,
            100.00,
            'Test Transfer'
        );

        // Store initial transaction counts for comprehensive validation
        $initialSenderTransactionCount = Transaction::where('user_id', $this->sender->id)->count();
        $initialReceiverTransactionCount = Transaction::where('user_id', $this->receiver->id)->count();

        // Act: Confirm the order
        $confirmedOrder = $this->ordersService->confirmOrder($order);

        // Assert: Verify order status changed
        $this->assertEquals(OrderStatus::COMPLETED, $confirmedOrder->status);
        $this->assertEquals($order->id, $confirmedOrder->id);
        
        // Assert: Verify database was updated
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::COMPLETED->value
        ]);
        
        // Assert: Verify wallet balances were updated correctly
        $this->sender->refresh();
        $this->receiver->refresh();
        
        // Sender balance should be reduced when order is confirmed
        $this->assertEquals(900.00, $this->sender->wallet_amount, 'Sender balance should decrease by transfer amount');
        $this->assertEquals(600.00, $this->receiver->wallet_amount, 'Receiver balance should increase by transfer amount');
        
        // Assert: Verify correct transactions were created by observer
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->sender->id,
            'type' => TransactionType::DEBIT->value,
            'amount' => -100.00,
            'order_id' => $order->id,
            'status' => TransactionStatus::ACTIVE->value
        ]);
        
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->receiver->id,
            'type' => TransactionType::CREDIT->value,
            'amount' => 100.00,
            'order_id' => $order->id,
            'status' => TransactionStatus::ACTIVE->value
        ]);

        // Assert: Verify transaction count increments are correct
        $finalSenderTransactionCount = Transaction::where('user_id', $this->sender->id)->count();
        $finalReceiverTransactionCount = Transaction::where('user_id', $this->receiver->id)->count();
        
        $this->assertEquals($initialSenderTransactionCount + 1, $finalSenderTransactionCount, 'Sender should have exactly one additional transaction');
        $this->assertEquals($initialReceiverTransactionCount + 1, $finalReceiverTransactionCount, 'Receiver should have exactly one additional transaction');
        
        // Assert: Verify observer dispatched the status changed event
        Event::assertDispatched(OrderStatusChanged::class, function ($event) use ($order) {
            return $event->order->id === $order->id 
                && $event->previousStatus === OrderStatus::PENDING_PAYMENT
                && $event->newStatus === OrderStatus::COMPLETED;
        });
        
        // Assert: Verify observer logging occurred
        Log::assertLogged('info', function ($message, $context) use ($order) {
            return strpos($message, 'Transactions created for completed order') !== false
                && isset($context['order_id']) 
                && $context['order_id'] === $order->id;
        });
        
        // Assert: Verify total transaction count for this specific order
        $allTransactionsForOrder = Transaction::where('order_id', $order->id)->count();
        $this->assertEquals(2, $allTransactionsForOrder, 'Should have exactly 2 transactions total (1 debit + 1 credit)');
        
        // Assert: Verify transaction descriptions contain order reference
        $debitTransaction = Transaction::where('user_id', $this->sender->id)->where('order_id', $order->id)->first();
        $creditTransaction = Transaction::where('user_id', $this->receiver->id)->where('order_id', $order->id)->first();
        
        $this->assertNotNull($debitTransaction, 'Debit transaction must exist');
        $this->assertNotNull($creditTransaction, 'Credit transaction must exist');
        $this->assertStringContainsString("Order #{$order->id}", $debitTransaction->description, 'Debit transaction should reference order ID');
        $this->assertStringContainsString("Order #{$order->id}", $creditTransaction->description, 'Credit transaction should reference order ID');
    }

    public function test_reject_pending_transfer_cancels_order_without_wallet_changes()
    {
        // Arrange: Set up event spy to test observer behavior
        Event::spy();
        
        // Arrange: Create a pending transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer to Reject'
        ]);

        // Store initial balances
        $initialSenderBalance = $this->sender->wallet_amount;
        $initialReceiverBalance = $this->receiver->wallet_amount;

        // Act: Reject the order
        $rejectedOrder = $this->ordersService->rejectOrder($order);

        // Assert: Verify order status changed to cancelled
        $this->assertEquals(OrderStatus::CANCELLED, $rejectedOrder->status);
        $this->assertEquals($order->id, $rejectedOrder->id);
        
        // Assert: Verify database was updated
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CANCELLED->value
        ]);
        
        // Assert: Verify wallet balances remain unchanged
        $this->sender->refresh();
        $this->receiver->refresh();
        
        $this->assertEquals($initialSenderBalance, $this->sender->wallet_amount, 'Sender balance should not change when transfer is rejected');
        $this->assertEquals($initialReceiverBalance, $this->receiver->wallet_amount, 'Receiver balance should not change when transfer is rejected');
        
        // Assert: Verify no transfer transactions were created
        $this->assertDatabaseMissing('transactions', [
            'order_id' => $order->id,
            'type' => TransactionType::DEBIT->value
        ]);
        
        $this->assertDatabaseMissing('transactions', [
            'order_id' => $order->id,
            'type' => TransactionType::CREDIT->value
        ]);
        
        // Assert: Verify that OrderCancelled event was dispatched
        Event::assertDispatched(OrderCancelled::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
        
        // Assert: Verify that OrderStatusChanged event was also dispatched by observer
        Event::assertDispatched(OrderStatusChanged::class, function ($event) use ($order) {
            return $event->order->id === $order->id 
                && $event->previousStatus === OrderStatus::PENDING_PAYMENT
                && $event->newStatus === OrderStatus::CANCELLED;
        });
        
        // Assert: Verify no transactions exist for this order
        $transactionCount = Transaction::where('order_id', $order->id)->count();
        $this->assertEquals(0, $transactionCount, 'No transactions should exist for rejected order');
    }

    public function test_cannot_confirm_already_completed_order()
    {
        // Arrange: Create a completed transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::COMPLETED,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Already Completed Transfer'
        ]);

        // Act & Assert: Attempt to confirm should throw validation exception
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Order cannot be confirmed in its current status.');
        
        $this->ordersService->confirmOrder($order);
    }

    public function test_cannot_reject_already_completed_order()
    {
        // Arrange: Create a completed transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::COMPLETED,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Already Completed Transfer'
        ]);

        // Act & Assert: Attempt to reject should throw validation exception
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Order cannot be rejected in its current status.');
        
        $this->ordersService->rejectOrder($order);
    }

    public function test_cannot_confirm_cancelled_order()
    {
        // Arrange: Create a cancelled transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::CANCELLED,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Cancelled Transfer'
        ]);

        // Act & Assert: Attempt to confirm should throw validation exception
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Order cannot be confirmed in its current status.');
        
        $this->ordersService->confirmOrder($order);
    }

    public function test_get_pending_transfers_returns_only_pending_transfers_for_receiver()
    {
        // Arrange: Create various orders
        $pendingTransfer1 = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Pending Transfer 1'
        ]);

        $pendingTransfer2 = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_APPROVAL,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 200.00,
            'title' => 'Pending Transfer 2'
        ]);

        // Create completed transfer (should not be included)
        Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::COMPLETED,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 150.00,
            'title' => 'Completed Transfer'
        ]);

        // Create transfer to different user (should not be included)
        $otherReceiver = User::factory()->create();
        Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $otherReceiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 75.00,
            'title' => 'Transfer to Other User'
        ]);

        // Act: Get pending transfers for receiver
        $pendingTransfers = $this->ordersService->getPendingTransfersReceived($this->receiver);

        // Assert: Verify correct transfers are returned
        $this->assertCount(2, $pendingTransfers, 'Should return exactly 2 pending transfers for this receiver');
        
        $transferIds = collect($pendingTransfers)->pluck('id')->toArray();
        $this->assertContains($pendingTransfer1->id, $transferIds, 'Should include first pending transfer');
        $this->assertContains($pendingTransfer2->id, $transferIds, 'Should include second pending transfer');
        
        // Assert: Verify transfers have correct status
        foreach ($pendingTransfers as $transfer) {
            $this->assertContains($transfer['status'], [OrderStatus::PENDING_PAYMENT->value, OrderStatus::PENDING_APPROVAL->value], 'All returned transfers should have pending status');
            $this->assertEquals($this->receiver->id, $transfer['receiver_user_id'], 'All transfers should be for the correct receiver');
            $this->assertEquals(OrderType::INTERNAL_TRANSFER->value, $transfer['order_type'], 'All transfers should be internal transfers');
        }
    }

    public function test_get_pending_transfers_returns_empty_when_no_pending_transfers()
    {
        // Arrange: Create only completed/cancelled transfers
        Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::COMPLETED,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Completed Transfer'
        ]);

        Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::CANCELLED,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 50.00,
            'title' => 'Cancelled Transfer'
        ]);

        // Act: Get pending transfers for receiver
        $pendingTransfers = $this->ordersService->getPendingTransfersReceived($this->receiver);

        // Assert: Should return empty array
        $this->assertEmpty($pendingTransfers, 'Should return empty array when no pending transfers exist');
        $this->assertIsArray($pendingTransfers, 'Should return an array');
    }

    public function test_get_pending_transfers_includes_user_relationships()
    {
        // Arrange: Create a pending transfer
        Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer with Relationships'
        ]);

        // Act: Get pending transfers
        $pendingTransfers = $this->ordersService->getPendingTransfersReceived($this->receiver);

        // Assert: Verify user data is loaded
        $this->assertCount(1, $pendingTransfers, 'Should return one pending transfer');
        
        $transfer = $pendingTransfers[0];
        
        $this->assertArrayHasKey('user', $transfer, 'Transfer should include sender user data');
        $this->assertArrayHasKey('receiver', $transfer, 'Transfer should include receiver user data');
        
        $this->assertEquals($this->sender->name, $transfer['user']['name'], 'Sender name should match');
        $this->assertEquals($this->sender->email, $transfer['user']['email'], 'Sender email should match');
        $this->assertEquals($this->receiver->name, $transfer['receiver']['name'], 'Receiver name should match');
        $this->assertEquals($this->receiver->email, $transfer['receiver']['email'], 'Receiver email should match');
    }

    public function test_multiple_transfers_between_same_users_process_correctly()
    {
        // Arrange: Create multiple transfers using proper service
        $orderService = app(\App\Services\OrderService::class);
        $order1 = $orderService->createInternalTransferOrder(
            $this->sender,
            $this->receiver,
            50.00,
            'First Transfer'
        );

        $order2 = $orderService->createInternalTransferOrder(
            $this->sender,
            $this->receiver,
            75.00,
            'Second Transfer'
        );

        // Act: Confirm first transfer
        $this->ordersService->confirmOrder($order1);
        
        $this->sender->refresh();
        $this->receiver->refresh();
        
        // Assert: Check balances after first transfer
        // Sender balance should be reduced by first transfer only: 1000 - 50 = 950
        $this->assertEquals(950.00, $this->sender->wallet_amount, 'Sender balance after first transfer');
        $this->assertEquals(550.00, $this->receiver->wallet_amount, 'Receiver balance after first transfer');
        
        // Act: Confirm second transfer
        $this->ordersService->confirmOrder($order2);
        
        $this->sender->refresh();
        $this->receiver->refresh();
        
        // Assert: Check final balances
        $this->assertEquals(875.00, $this->sender->wallet_amount, 'Sender final balance');
        $this->assertEquals(625.00, $this->receiver->wallet_amount, 'Receiver final balance');
        
        // Assert: Verify correct number of transactions
        $senderTransactions = Transaction::where('user_id', $this->sender->id)
            ->whereIn('order_id', [$order1->id, $order2->id])
            ->count();
        $this->assertEquals(2, $senderTransactions, 'Sender should have 2 debit transactions');
        
        $receiverTransactions = Transaction::where('user_id', $this->receiver->id)
            ->whereIn('order_id', [$order1->id, $order2->id])
            ->count();
        $this->assertEquals(2, $receiverTransactions, 'Receiver should have 2 credit transactions');
    }

    public function test_reverse_transfers_process_correctly()
    {
        // Arrange: Create a transfer from receiver to sender (reverse direction)
        $orderService = app(\App\Services\OrderService::class);
        $order = $orderService->createInternalTransferOrder(
            $this->receiver, // Original receiver is now the sender
            $this->sender,   // Original sender is now the receiver
            200.00,
            'Reverse Transfer'
        );

        // Store initial balances (order creation doesn't change balances immediately)
        $initialSenderBalance = $this->sender->wallet_amount; // Still 1000.00 
        $initialReceiverBalance = $this->receiver->wallet_amount; // Still 500.00

        // Act: Confirm the reverse transfer
        $this->ordersService->confirmOrder($order);

        // Refresh users
        $this->sender->refresh();
        $this->receiver->refresh();

        // Assert: Verify wallet balances were updated correctly
        $this->assertEquals($initialReceiverBalance - 200.00, $this->receiver->wallet_amount, 'Original receiver (now sender) should have less money');
        $this->assertEquals($initialSenderBalance + 200.00, $this->sender->wallet_amount, 'Original sender (now receiver) should have more money');
        
        // Assert: Verify transactions were created correctly
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->receiver->id, // Debit from the actual sender
            'type' => TransactionType::DEBIT->value,
            'amount' => -200.00,
            'order_id' => $order->id,
            'status' => TransactionStatus::ACTIVE->value
        ]);
        
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->sender->id, // Credit to the actual receiver
            'type' => TransactionType::CREDIT->value,
            'amount' => 200.00,
            'order_id' => $order->id,
            'status' => TransactionStatus::ACTIVE->value
        ]);
    }
}