<?php

namespace Tests\Unit\Services\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\TopUpProvider;
use App\Models\User;
use App\Services\Merchant\OrdersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrdersServiceAdminTest extends TestCase
{
    use RefreshDatabase;

    protected OrdersService $ordersService;
    protected User $adminUser;
    protected User $targetUser;
    protected TopUpProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->ordersService = app(OrdersService::class);
        
        $this->adminUser = User::factory()->create();
        $this->targetUser = User::factory()->create([
            'wallet_amount' => 0.00
        ]);
        
        // Create an initial transaction to give the user a 50.00 balance
        \App\Models\Transaction::create([
            'user_id' => $this->adminUser->id, // Temp user_id
            'type' => \App\Enums\TransactionType::CREDIT,
            'amount' => 50.00,
            'status' => \App\Enums\TransactionStatus::ACTIVE,
            'description' => 'Initial balance',
            'created_by' => $this->adminUser->id,
        ]);
        // Update to correct user_id after creation to avoid observer triggering during setup
        \App\Models\Transaction::where('description', 'Initial balance')->update(['user_id' => $this->targetUser->id]);
        $this->targetUser->update(['wallet_amount' => 50.00]); // Set the initial balance manually
        
        $this->provider = TopUpProvider::factory()->create([
            'name' => 'Test Provider',
            'code' => 'TEST', 
            'is_active' => true
        ]);
    }

    public function test_creates_admin_top_up_order_successfully()
    {
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'description' => 'Manual top-up by admin',
            'top_up_provider_id' => $this->provider->id
        ];

        $order = $this->ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($this->targetUser->id, $order->user_id);
        $this->assertEquals(OrderType::ADMIN_TOP_UP, $order->order_type);
        $this->assertEquals(OrderStatus::COMPLETED, $order->status);
        $this->assertEquals(100.00, $order->amount);
        $this->assertEquals('Admin Top-up', $order->title);
        $this->assertEquals($this->provider->id, $order->top_up_provider_id);
    }

    public function test_validates_required_fields()
    {
        $data = [
            'amount' => 100.00,
            // Missing title
        ];

        $this->expectException(ValidationException::class);
        $this->ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);
    }

    public function test_validates_minimum_amount()
    {
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 0.00,
            'top_up_provider_id' => $this->provider->id
        ];

        $this->expectException(ValidationException::class);
        $this->ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);
    }

    public function test_validates_amount_limit()
    {
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100000.00, // Exceeds limit
            'top_up_provider_id' => $this->provider->id
        ];

        $this->expectException(ValidationException::class);
        $this->ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);
    }

    public function test_validates_top_up_provider_exists()
    {
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'top_up_provider_id' => 9999 // Non-existent
        ];

        $this->expectException(ValidationException::class);
        $this->ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);
    }

    public function test_validates_active_top_up_provider()
    {
        $inactiveProvider = TopUpProvider::factory()->create([
            'is_active' => false
        ]);

        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'top_up_provider_id' => $inactiveProvider->id
        ];

        $this->expectException(ValidationException::class);
        $this->ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);
    }

    public function test_updates_user_wallet_after_successful_order()
    {
        $initialBalance = $this->targetUser->wallet_amount;
        $this->assertEquals(50.00, $initialBalance); // Verify initial balance
        
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'top_up_provider_id' => $this->provider->id
        ];

        $order = $this->ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);

        // Refresh from database to get latest state
        $this->targetUser->refresh();
        
        $this->assertEquals($initialBalance + 100.00, $this->targetUser->wallet_amount);
    }

    public function test_creates_transaction_record()
    {
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'top_up_provider_id' => $this->provider->id
        ];

        $order = $this->ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->targetUser->id,
            'order_id' => $order->id,
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'active',
            'created_by' => $this->adminUser->id
        ]);
    }

    public function test_works_without_provider_reference()
    {
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'top_up_provider_id' => $this->provider->id
            // No provider_reference
        ];

        $order = $this->ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);

        $this->assertNull($order->provider_reference);
    }

    public function test_includes_provider_reference_when_provided()
    {
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'top_up_provider_id' => $this->provider->id,
            'provider_reference' => 'REF123'
        ];

        $order = $this->ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);

        $this->assertEquals('REF123', $order->provider_reference);
    }
}