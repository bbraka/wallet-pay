<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\TopUpProvider;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WalletOperationsTest extends TestCase
{
    use DatabaseTransactions;

    private OrderService $orderService;
    private User $sender;
    private User $receiver;
    private User $admin;
    private TopUpProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
        $walletService = app(\App\Services\WalletTransactionService::class);
        
        $this->sender = User::factory()->create(['wallet_amount' => 0.00]);
        $this->receiver = User::factory()->create(['wallet_amount' => 0.00]);
        $this->admin = User::factory()->create(['wallet_amount' => 0.00]);
        
        // Add initial balances through transactions
        $walletService->add($this->sender, 200.00, 'Initial balance');
        $walletService->add($this->receiver, 50.00, 'Initial balance');
        
        $this->provider = TopUpProvider::firstOrCreate(
            ['code' => 'TEST_BANK'],
            [
                'name' => 'Test Bank',
                'description' => 'Test bank provider',
                'is_active' => true,
                'requires_reference' => false, // Changed to false for admin test
            ]
        );
    }

    public function test_internal_transfer_workflow(): void
    {
        // Create internal transfer order
        $order = $this->orderService->createInternalTransferOrder(
            $this->sender,
            $this->receiver,
            100.00,
            'Test Transfer'
        );

        $this->assertEquals(OrderType::INTERNAL_TRANSFER, $order->order_type);
        $this->assertEquals(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertEquals($this->sender->id, $order->user_id);
        $this->assertEquals($this->receiver->id, $order->receiver_user_id);

        // Check sender's balance is unchanged during order creation
        $this->sender->refresh();
        $this->assertEquals(200.00, $this->sender->wallet_amount);

        // Confirm payment
        $this->orderService->confirmPayment($order, $this->receiver);
        
        $order->refresh();
        $this->assertEquals(OrderStatus::COMPLETED, $order->status);

        // Check receiver's balance was increased
        $this->receiver->refresh();
        $this->assertEquals(150.00, $this->receiver->wallet_amount);
    }

    public function test_user_top_up_workflow(): void
    {
        // Create a provider that requires reference for this test
        $bankProvider = TopUpProvider::firstOrCreate(
            ['code' => 'BANK_PROVIDER'],
            [
                'name' => 'Bank Provider',
                'description' => 'Bank provider that requires reference',
                'is_active' => true,
                'requires_reference' => true,
            ]
        );
        
        $order = $this->orderService->createUserTopUpOrder(
            $this->sender,
            75.00,
            'Bank Top-up',
            $bankProvider,
            'REF123456'
        );

        $this->assertEquals(OrderType::USER_TOP_UP, $order->order_type);
        $this->assertEquals(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertEquals($bankProvider->id, $order->top_up_provider_id);
        $this->assertEquals('REF123456', $order->provider_reference);

        // Balance should not change until confirmed
        $this->sender->refresh();
        $this->assertEquals(200.00, $this->sender->wallet_amount);

        // Confirm payment
        $this->orderService->confirmPayment($order, $this->sender);
        
        $order->refresh();
        $this->assertEquals(OrderStatus::COMPLETED, $order->status);

        // Check balance was increased
        $this->sender->refresh();
        $this->assertEquals(275.00, $this->sender->wallet_amount);
    }

    public function test_admin_top_up_completes_immediately(): void
    {
        $originalBalance = $this->receiver->wallet_amount;

        $order = $this->orderService->createAdminTopUpOrder(
            $this->receiver,
            $this->admin,
            50.00,
            'Admin Adjustment',
            $this->provider
        );

        $this->assertEquals(OrderType::ADMIN_TOP_UP, $order->order_type);
        $this->assertEquals(OrderStatus::COMPLETED, $order->status);

        // Check balance was immediately increased
        $this->receiver->refresh();
        $this->assertEquals($originalBalance + 50.00, $this->receiver->wallet_amount);
    }

    public function test_order_rejection_refunds_sender(): void
    {
        $order = $this->orderService->createInternalTransferOrder(
            $this->sender,
            $this->receiver,
            80.00,
            'Test Transfer to Reject'
        );

        // Check sender's balance is unchanged during order creation
        $this->sender->refresh();
        $this->assertEquals(200.00, $this->sender->wallet_amount);

        // Reject payment
        $this->orderService->rejectPayment($order, $this->receiver);
        
        $order->refresh();
        $this->assertEquals(OrderStatus::CANCELLED, $order->status);

        // Check sender's balance was restored
        $this->sender->refresh();
        $this->assertEquals(200.00, $this->sender->wallet_amount);

        // Check receiver's balance unchanged
        $this->receiver->refresh();
        $this->assertEquals(50.00, $this->receiver->wallet_amount);
    }
}
