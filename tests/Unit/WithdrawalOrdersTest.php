<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\WithdrawalRequested;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WalletTransactionService;
use App\Exceptions\Wallet\InsufficientBalanceException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WithdrawalOrdersTest extends TestCase
{
    use DatabaseTransactions;

    private OrderService $orderService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Mock wallet service to have sufficient balance
        $walletService = $this->createMock(WalletTransactionService::class);
        $walletService->method('hasSufficientBalance')->willReturn(true);
        $walletService->method('calculateUserBalance')->willReturn(1000.00);
        
        $this->app->instance(WalletTransactionService::class, $walletService);
        
        // Create OrderService after setting up the mock
        $this->orderService = app(OrderService::class);
    }

    public function test_can_create_user_withdrawal_request()
    {
        Event::fake();

        $order = $this->orderService->processWithdrawalRequest(
            $this->user,
            100.00,
            'Test withdrawal',
            OrderType::USER_WITHDRAWAL
        );

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(OrderType::USER_WITHDRAWAL, $order->order_type);
        $this->assertEquals(OrderStatus::PENDING_APPROVAL, $order->status);
        $this->assertEquals(100.00, $order->amount);
        $this->assertEquals($this->user->id, $order->user_id);
    }

    public function test_withdrawal_request_requires_positive_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Withdrawal amount must be positive');

        $this->orderService->processWithdrawalRequest(
            $this->user,
            -50.00,
            'Invalid withdrawal',
            OrderType::USER_WITHDRAWAL
        );
    }

    public function test_user_withdrawal_checks_balance()
    {
        // Mock insufficient balance - create new service instance
        $walletService = $this->createMock(WalletTransactionService::class);
        $walletService->method('hasSufficientBalance')->willReturn(false);
        $walletService->method('calculateUserBalance')->willReturn(50.00);
        
        $orderService = new OrderService($walletService);

        $this->expectException(InsufficientBalanceException::class);

        $orderService->processWithdrawalRequest(
            $this->user,
            100.00,
            'Insufficient funds withdrawal',
            OrderType::USER_WITHDRAWAL
        );
    }

    public function test_can_approve_withdrawal()
    {
        Event::fake();

        // Create withdrawal order
        $order = $this->orderService->processWithdrawalRequest(
            $this->user,
            100.00,
            'Test withdrawal',
            OrderType::USER_WITHDRAWAL
        );

        // Approve withdrawal
        $this->orderService->approveWithdrawal($order);

        $order->refresh();
        $this->assertEquals(OrderStatus::COMPLETED, $order->status);
        // Note: payment_completion_date is set by event listener, which is faked
        // so we won't test it here, but we know the status changed correctly
    }

    public function test_can_deny_withdrawal()
    {
        Event::fake();

        // Create withdrawal order
        $order = $this->orderService->processWithdrawalRequest(
            $this->user,
            100.00,
            'Test withdrawal',
            OrderType::USER_WITHDRAWAL
        );

        // Deny withdrawal
        $this->orderService->denyWithdrawal($order, 'Suspicious activity');

        $order->refresh();
        $this->assertEquals(OrderStatus::CANCELLED, $order->status);
        $this->assertStringContainsString('Denied: Suspicious activity', $order->description);
    }

    public function test_admin_withdrawal_bypasses_balance_check()
    {
        // Mock insufficient balance - create new service instance
        $walletService = $this->createMock(WalletTransactionService::class);
        $walletService->method('hasSufficientBalance')->willReturn(false);
        $walletService->method('calculateUserBalance')->willReturn(50.00);
        
        $orderService = new OrderService($walletService);

        // Admin withdrawal should still work
        $order = $orderService->processWithdrawalRequest(
            $this->user,
            100.00,
            'Admin refund',
            OrderType::ADMIN_WITHDRAWAL
        );

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(OrderType::ADMIN_WITHDRAWAL, $order->order_type);
        $this->assertEquals(OrderStatus::PENDING_APPROVAL, $order->status);
    }

    public function test_withdrawal_methods_validate_order_type()
    {
        // Create a non-withdrawal order
        $order = Order::factory()->create([
            'order_type' => OrderType::USER_TOP_UP,
            'status' => OrderStatus::PENDING_PAYMENT
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order is not a withdrawal order');

        $this->orderService->approveWithdrawal($order);
    }
}
