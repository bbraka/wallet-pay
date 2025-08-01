<?php

namespace Tests\Unit\Observers;

use App\Models\Order;
use App\Models\User;
use App\Models\TopUpProvider;
use App\Models\Transaction;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\TransactionType;
use App\Enums\TransactionStatus;
use App\Events\OrderStatusChanged;
use App\Observers\OrderObserver;
use App\Services\WalletTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class OrderObserverTest extends TestCase
{
    use RefreshDatabase;

    private OrderObserver $observer;
    private WalletTransactionService $walletService;
    private User $user;
    private User $receiver;
    private TopUpProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->walletService = Mockery::mock(WalletTransactionService::class);
        $this->observer = new OrderObserver($this->walletService);
        
        // Create test users
        $this->user = User::factory()->create([
            'wallet_amount' => 1000.00
        ]);
        
        $this->receiver = User::factory()->create([
            'wallet_amount' => 500.00
        ]);
        
        // Create test provider
        $this->provider = TopUpProvider::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dispatches_order_status_changed_event_when_status_changes()
    {
        Event::fake();
        
        // Create order with pending status
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::USER_TOP_UP,
            'top_up_provider_id' => $this->provider->id,
            'amount' => 100.00
        ]);

        // Mock wallet service expectations (since observer creates transactions)
        $this->walletService
            ->shouldReceive('add')
            ->once()
            ->with(
                Mockery::type(User::class),
                100.00,
                Mockery::pattern("/Top-up via .* - Order #\d+/"),
                Mockery::type(Order::class)
            );

        // Mock the original status
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'status' => OrderStatus::PENDING_PAYMENT->value
        ]));
        $order->syncOriginal();
        
        // Change status and mark as dirty
        $order->status = OrderStatus::COMPLETED;
        $order->syncChanges();

        // Call observer directly
        $this->observer->updated($order);

        // Assert event was dispatched
        Event::assertDispatched(OrderStatusChanged::class, function ($event) use ($order) {
            return $event->order->id === $order->id &&
                   $event->previousStatus === OrderStatus::PENDING_PAYMENT &&
                   $event->newStatus === OrderStatus::COMPLETED;
        });
    }

    public function test_does_not_dispatch_event_when_status_does_not_change()
    {
        Event::fake();
        
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::COMPLETED,
            'order_type' => OrderType::USER_TOP_UP
        ]);

        // Update something other than status - don't mark status as dirty
        $order->description = 'Updated description';

        // Call observer directly
        $this->observer->updated($order);

        // Assert no event was dispatched
        Event::assertNotDispatched(OrderStatusChanged::class);
    }

    public function test_creates_transactions_for_completed_internal_transfer()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00
        ]);

        // Mock wallet service expectations
        $this->walletService
            ->shouldReceive('withdraw')
            ->once()
            ->with(
                Mockery::type(User::class),
                100.00,
                Mockery::pattern("/Transfer to .* - Order #\d+/"),
                Mockery::type(Order::class)
            );

        $this->walletService
            ->shouldReceive('add')
            ->once()
            ->with(
                Mockery::type(User::class),
                100.00,
                Mockery::pattern("/Transfer from .* - Order #\d+/"),
                Mockery::type(Order::class)
            );

        // Set up the order to simulate status change
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'status' => OrderStatus::PENDING_PAYMENT->value
        ]));
        $order->syncOriginal();
        
        $order->status = OrderStatus::COMPLETED;
        $order->syncChanges();

        // Call observer directly
        $this->observer->updated($order);
    }

    public function test_creates_transaction_for_completed_top_up()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::USER_TOP_UP,
            'top_up_provider_id' => $this->provider->id,
            'amount' => 100.00
        ]);

        // Mock wallet service expectations
        $this->walletService
            ->shouldReceive('add')
            ->once()
            ->with(
                Mockery::type(User::class),
                100.00,
                Mockery::pattern("/Top-up via .* - Order #\d+/"),
                Mockery::type(Order::class)
            );

        // Set up the order to simulate status change
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'status' => OrderStatus::PENDING_PAYMENT->value
        ]));
        $order->syncOriginal();
        
        $order->status = OrderStatus::COMPLETED;
        $order->syncChanges();

        // Call observer directly
        $this->observer->updated($order);
    }

    public function test_creates_transaction_for_completed_withdrawal()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING_APPROVAL,
            'order_type' => OrderType::USER_WITHDRAWAL,
            'amount' => 100.00
        ]);

        // Mock wallet service expectations
        $this->walletService
            ->shouldReceive('withdraw')
            ->once()
            ->with(
                Mockery::type(User::class),
                100.00,
                Mockery::pattern("/Withdrawal - Order #\d+/"),
                Mockery::type(Order::class)
            );

        // Set up the order to simulate status change
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'status' => OrderStatus::PENDING_APPROVAL->value
        ]));
        $order->syncOriginal();
        
        $order->status = OrderStatus::COMPLETED;
        $order->syncChanges();

        // Call observer directly
        $this->observer->updated($order);
    }

    public function test_does_not_create_transactions_for_admin_top_up()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING_APPROVAL,
            'order_type' => OrderType::ADMIN_TOP_UP,
            'top_up_provider_id' => $this->provider->id,
            'amount' => 100.00
        ]);

        // Should not call wallet service for admin top-ups
        $this->walletService->shouldNotReceive('add');
        $this->walletService->shouldNotReceive('withdraw');

        // Set up the order to simulate status change
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'status' => OrderStatus::PENDING_APPROVAL->value
        ]));
        $order->syncOriginal();
        
        $order->status = OrderStatus::COMPLETED;
        $order->syncChanges();

        // Call observer directly
        $this->observer->updated($order);
    }

    public function test_does_not_create_transactions_when_order_was_already_completed()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::COMPLETED,
            'order_type' => OrderType::USER_TOP_UP,
            'top_up_provider_id' => $this->provider->id,
            'amount' => 100.00
        ]);

        // Should not call wallet service when already completed
        $this->walletService->shouldNotReceive('add');
        $this->walletService->shouldNotReceive('withdraw');

        // Set up the order to simulate status change (completed -> refunded)
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'status' => OrderStatus::COMPLETED->value
        ]));
        $order->syncOriginal();
        
        $order->status = OrderStatus::REFUNDED;
        $order->syncChanges();

        // Call observer directly
        $this->observer->updated($order);
    }

    public function test_throws_exception_for_internal_transfer_without_receiver()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'receiver_user_id' => null, // Missing receiver
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Internal transfer order missing receiver_user_id');

        // Set up the order to simulate status change
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'status' => OrderStatus::PENDING_PAYMENT->value
        ]));
        $order->syncOriginal();
        
        $order->status = OrderStatus::COMPLETED;
        $order->syncChanges();

        // Call observer directly - should throw exception
        $this->observer->updated($order);
    }

    public function test_logs_success_when_transactions_are_created()
    {
        Log::spy();

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::USER_TOP_UP,
            'top_up_provider_id' => $this->provider->id,
            'amount' => 100.00
        ]);

        $this->walletService
            ->shouldReceive('add')
            ->once();

        // Set up the order to simulate status change
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'status' => OrderStatus::PENDING_PAYMENT->value
        ]));
        $order->syncOriginal();
        
        $order->status = OrderStatus::COMPLETED;
        $order->syncChanges();

        // Call observer directly
        $this->observer->updated($order);

        // Assert success was logged
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Transactions created for completed order', [
                'order_id' => $order->id,
                'order_type' => OrderType::USER_TOP_UP->value,
                'amount' => 100.00,
                'user_id' => $this->user->id,
            ]);
    }

    public function test_logs_error_and_rethrows_exception_when_transaction_creation_fails()
    {
        Log::spy();

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::USER_TOP_UP,
            'top_up_provider_id' => $this->provider->id,
            'amount' => 100.00
        ]);

        $exception = new \Exception('Wallet service error');
        $this->walletService
            ->shouldReceive('add')
            ->once()
            ->andThrow($exception);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Wallet service error');

        // Set up the order to simulate status change
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'status' => OrderStatus::PENDING_PAYMENT->value
        ]));
        $order->syncOriginal();
        
        $order->status = OrderStatus::COMPLETED;
        $order->syncChanges();

        // Call observer directly - should throw exception
        $this->observer->updated($order);

        // Assert error was logged
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Failed to create transactions for completed order', [
                'order_id' => $order->id,
                'order_type' => OrderType::USER_TOP_UP->value,
                'error' => 'Wallet service error',
            ]);
    }

    public function test_only_creates_transactions_when_transitioning_to_completed()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::USER_TOP_UP,
            'top_up_provider_id' => $this->provider->id,
            'amount' => 100.00
        ]);

        // Should not call wallet service when transitioning to cancelled
        $this->walletService->shouldNotReceive('add');
        $this->walletService->shouldNotReceive('withdraw');

        // Set up the order to simulate status change (pending -> cancelled)
        $order->setRawAttributes(array_merge($order->getAttributes(), [
            'status' => OrderStatus::PENDING_PAYMENT->value
        ]));
        $order->syncOriginal();
        
        $order->status = OrderStatus::CANCELLED;
        $order->syncChanges();

        // Call observer directly
        $this->observer->updated($order);

        // Verify no transactions were created
        $this->assertEquals(0, Transaction::where('order_id', $order->id)->count());
    }
}