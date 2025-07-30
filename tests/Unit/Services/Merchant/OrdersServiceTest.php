<?php

namespace Tests\Unit\Services\Merchant;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Models\Order;
use App\Models\TopUpProvider;
use App\Models\User;
use App\Services\Merchant\OrdersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrdersServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrdersService $service;
    protected User $user;
    protected User $otherUser;
    protected TopUpProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new OrdersService();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->provider = TopUpProvider::factory()->create(['is_active' => true]);
    }

    public function test_generates_descriptive_order_descriptions(): void
    {
        $adminUser = User::factory()->create(['email' => 'admin@test.com']);
        $targetUser = User::factory()->create(['email' => 'user@test.com']);
        $receiverUser = User::factory()->create(['email' => 'receiver@test.com']);

        // Test admin top-up description
        $adminDescription = $this->service->generateOrderDescription(
            OrderType::ADMIN_TOP_UP,
            $targetUser,
            null,
            123
        );
        $this->assertEquals('Admin top-up for user@test.com - Order #123', $adminDescription);

        // Test user top-up description
        $userTopUpDescription = $this->service->generateOrderDescription(
            OrderType::USER_TOP_UP,
            $targetUser,
            null,
            456
        );
        $this->assertEquals('Order purchased funds #456 - User top-up by user@test.com', $userTopUpDescription);

        // Test internal transfer description
        $transferDescription = $this->service->generateOrderDescription(
            OrderType::INTERNAL_TRANSFER,
            $targetUser,
            $receiverUser,
            789
        );
        $this->assertEquals('Received funds from user@test.com to receiver@test.com', $transferDescription);

        // Test user withdrawal description
        $withdrawalDescription = $this->service->generateOrderDescription(
            OrderType::USER_WITHDRAWAL,
            $targetUser,
            null,
            101
        );
        $this->assertEquals('User withdrawal request by user@test.com - Order #101', $withdrawalDescription);

        // Test custom description takes precedence
        $customDescription = $this->service->generateOrderDescription(
            OrderType::ADMIN_TOP_UP,
            $targetUser,
            null,
            123,
            'Custom description provided'
        );
        $this->assertEquals('Custom description provided', $customDescription);
    }

    public function test_admin_top_up_uses_generated_description(): void
    {
        Event::fake();
        $adminUser = User::factory()->create(['email' => 'admin@test.com']);
        $targetUser = User::factory()->create(['email' => 'user@test.com']);

        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100,
            'top_up_provider_id' => $this->provider->id,
        ];

        $order = $this->service->createAdminTopUp($targetUser, $data, $adminUser);

        $this->assertStringContainsString('Admin top-up for user@test.com', $order->description);
        $this->assertStringContainsString("Order #{$order->id}", $order->description);
    }

    public function test_can_create_top_up_order(): void
    {
        Event::fake();

        $data = [
            'title' => 'Test Top-up',
            'amount' => 100,
            'top_up_provider_id' => $this->provider->id,
        ];

        $order = $this->service->createOrder($this->user, $data);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(OrderType::USER_TOP_UP, $order->order_type);
        $this->assertEquals(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertEquals($this->user->id, $order->user_id);
        
        // Test that description is generated correctly for user top-up
        $this->assertStringContainsString('Order purchased funds', $order->description);
        $this->assertStringContainsString("#{$order->id}", $order->description);

        Event::assertDispatched(OrderCreated::class);
    }

    public function test_can_create_transfer_order(): void
    {
        Event::fake();

        $data = [
            'title' => 'Test Transfer',
            'amount' => 100,
            'receiver_user_id' => $this->otherUser->id,
        ];

        $order = $this->service->createOrder($this->user, $data);

        $this->assertEquals(OrderType::INTERNAL_TRANSFER, $order->order_type);
        $this->assertEquals($this->otherUser->id, $order->receiver_user_id);

        Event::assertDispatched(OrderCreated::class);
    }

    public function test_cannot_create_order_exceeding_top_up_limit(): void
    {
        $data = [
            'title' => 'Large Top-up',
            'amount' => Order::MAX_TOP_UP_AMOUNT + 1,
            'top_up_provider_id' => $this->provider->id,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createOrder($this->user, $data);
    }

    public function test_cannot_create_order_exceeding_transfer_limit(): void
    {
        $data = [
            'title' => 'Large Transfer',
            'amount' => Order::MAX_TRANSFER_AMOUNT + 1,
            'receiver_user_id' => $this->otherUser->id,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createOrder($this->user, $data);
    }

    public function test_cannot_create_transfer_to_self(): void
    {
        $data = [
            'title' => 'Self Transfer',
            'amount' => 100,
            'receiver_user_id' => $this->user->id,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createOrder($this->user, $data);
    }

    public function test_cannot_create_transfer_to_nonexistent_user(): void
    {
        $data = [
            'title' => 'Invalid Transfer',
            'amount' => 100,
            'receiver_user_id' => 99999,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createOrder($this->user, $data);
    }

    public function test_cannot_create_order_with_inactive_provider(): void
    {
        $inactiveProvider = TopUpProvider::factory()->create(['is_active' => false]);

        $data = [
            'title' => 'Test Top-up',
            'amount' => 100,
            'top_up_provider_id' => $inactiveProvider->id,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createOrder($this->user, $data);
    }

    public function test_cannot_create_order_with_zero_amount(): void
    {
        $data = [
            'title' => 'Zero Amount',
            'amount' => 0,
            'top_up_provider_id' => $this->provider->id,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createOrder($this->user, $data);
    }

    public function test_cannot_create_order_with_negative_amount(): void
    {
        $data = [
            'title' => 'Negative Amount',
            'amount' => -100,
            'top_up_provider_id' => $this->provider->id,
        ];

        $this->expectException(ValidationException::class);
        $this->service->createOrder($this->user, $data);
    }

    public function test_can_update_pending_order(): void
    {
        Event::fake();

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::USER_TOP_UP,
            'amount' => 100,
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'amount' => 200,
        ];

        $updatedOrder = $this->service->updateOrder($order, $updateData);

        $this->assertEquals('Updated Title', $updatedOrder->title);
        $this->assertEquals(200, $updatedOrder->amount);

        Event::assertDispatched(OrderUpdated::class);
    }

    public function test_cannot_update_completed_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::COMPLETED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->updateOrder($order, ['title' => 'Updated']);
    }

    public function test_cannot_update_order_exceeding_amount_limit(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::USER_TOP_UP,
        ]);

        $updateData = ['amount' => Order::MAX_TOP_UP_AMOUNT + 1];

        $this->expectException(ValidationException::class);
        $this->service->updateOrder($order, $updateData);
    }

    public function test_can_cancel_pending_order(): void
    {
        Event::fake();

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $cancelledOrder = $this->service->cancelOrder($order);

        $this->assertEquals(OrderStatus::CANCELLED, $cancelledOrder->status);

        Event::assertDispatched(OrderCancelled::class);
    }

    public function test_cannot_cancel_completed_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::COMPLETED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->cancelOrder($order);
    }

    public function test_get_validation_rules_returns_correct_structure(): void
    {
        $rules = $this->service->getValidationRules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('max_top_up_amount', $rules);
        $this->assertArrayHasKey('max_transfer_amount', $rules);
        $this->assertArrayHasKey('required_fields', $rules);
        $this->assertArrayHasKey('allowed_statuses', $rules);

        $this->assertEquals(Order::MAX_TOP_UP_AMOUNT, $rules['max_top_up_amount']);
        $this->assertEquals(Order::MAX_TRANSFER_AMOUNT, $rules['max_transfer_amount']);
    }

    public function test_get_orders_with_filters_applies_date_filter(): void
    {
        Order::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(5)
        ]);
        Order::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDay()
        ]);

        $filters = ['date_from' => now()->subDays(2)->format('Y-m-d')];
        $result = $this->service->getOrdersWithFilters($this->user, $filters);

        $this->assertEquals(1, $result->total());
    }

    public function test_get_orders_with_filters_applies_amount_filter(): void
    {
        Order::factory()->create(['user_id' => $this->user->id, 'amount' => 100]);
        Order::factory()->create(['user_id' => $this->user->id, 'amount' => 500]);

        $filters = ['min_amount' => 200];
        $result = $this->service->getOrdersWithFilters($this->user, $filters);

        $this->assertEquals(1, $result->total());
    }

    public function test_get_orders_with_filters_applies_status_filter(): void
    {
        Order::factory()->create(['user_id' => $this->user->id, 'status' => OrderStatus::PENDING_PAYMENT]);
        Order::factory()->create(['user_id' => $this->user->id, 'status' => OrderStatus::COMPLETED]);

        $filters = ['status' => OrderStatus::COMPLETED->value];
        $result = $this->service->getOrdersWithFilters($this->user, $filters);

        $this->assertEquals(1, $result->total());
    }

    public function test_get_orders_with_filters_only_returns_user_orders(): void
    {
        Order::factory()->create(['user_id' => $this->user->id]);
        Order::factory()->create(['user_id' => $this->otherUser->id]);

        $result = $this->service->getOrdersWithFilters($this->user, []);

        $this->assertEquals(1, $result->total());
    }
}