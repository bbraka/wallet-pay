<?php

namespace Tests\Feature\Merchant;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\TopUpProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OrdersControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected User $otherUser;
    protected TopUpProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->provider = TopUpProvider::factory()->create(['is_active' => true]);
    }

    public function test_user_can_list_their_orders(): void
    {
        Order::factory()->create(['user_id' => $this->user->id]);
        Order::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/merchant/orders');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_filter_orders_by_date_range(): void
    {
        Order::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(5)
        ]);
        Order::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(1)
        ]);

        $dateFrom = now()->subDays(2)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/merchant/orders?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_filter_orders_by_amount(): void
    {
        Order::factory()->create(['user_id' => $this->user->id, 'amount' => 100]);
        Order::factory()->create(['user_id' => $this->user->id, 'amount' => 500]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/merchant/orders?min_amount=200&max_amount=1000');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_filter_orders_by_status(): void
    {
        Order::factory()->create(['user_id' => $this->user->id, 'status' => OrderStatus::PENDING_PAYMENT]);
        Order::factory()->create(['user_id' => $this->user->id, 'status' => OrderStatus::COMPLETED]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/merchant/orders?status=completed');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_create_top_up_order(): void
    {
        $orderData = [
            'title' => 'Test Top-up',
            'amount' => 100,
            'description' => 'Test description',
            'top_up_provider_id' => $this->provider->id,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/merchant/orders', $orderData);

        $response->assertCreated()
            ->assertJsonFragment([
                'title' => 'Test Top-up',
                'amount' => '100.00',
                'order_type' => 'user_top_up',
                'status' => 'pending_payment',
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('orders', [
            'title' => 'Test Top-up',
            'user_id' => $this->user->id,
            'order_type' => OrderType::USER_TOP_UP,
        ]);
    }

    public function test_user_can_create_transfer_order(): void
    {
        $orderData = [
            'title' => 'Test Transfer',
            'amount' => 100,
            'description' => 'Test transfer',
            'receiver_user_id' => $this->otherUser->id,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/merchant/orders', $orderData);

        $response->assertCreated()
            ->assertJsonFragment([
                'title' => 'Test Transfer',
                'amount' => '100.00',
                'order_type' => 'internal_transfer',
                'receiver_user_id' => $this->otherUser->id,
            ]);
    }

    public function test_cannot_create_order_with_invalid_amount(): void
    {
        $orderData = [
            'title' => 'Invalid Order',
            'amount' => -100,
            'top_up_provider_id' => $this->provider->id,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/merchant/orders', $orderData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_cannot_create_top_up_order_exceeding_limit(): void
    {
        $orderData = [
            'title' => 'Large Top-up',
            'amount' => Order::MAX_TOP_UP_AMOUNT + 1,
            'top_up_provider_id' => $this->provider->id,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/merchant/orders', $orderData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_cannot_create_transfer_order_exceeding_limit(): void
    {
        $orderData = [
            'title' => 'Large Transfer',
            'amount' => Order::MAX_TRANSFER_AMOUNT + 1,
            'receiver_user_id' => $this->otherUser->id,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/merchant/orders', $orderData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_cannot_transfer_to_self(): void
    {
        $orderData = [
            'title' => 'Self Transfer',
            'amount' => 100,
            'receiver_user_id' => $this->user->id,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/merchant/orders', $orderData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['receiver_user_id']);
    }

    public function test_cannot_create_order_without_provider_or_receiver(): void
    {
        $orderData = [
            'title' => 'Invalid Order',
            'amount' => 100,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/merchant/orders', $orderData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_user_can_view_specific_order(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/merchant/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $order->id]);
    }

    public function test_user_cannot_view_other_users_order(): void
    {
        $order = Order::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/merchant/orders/{$order->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_user_can_update_pending_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'title' => 'Original Title',
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'amount' => 200,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/merchant/orders/{$order->id}", $updateData);

        $response->assertOk()
            ->assertJsonFragment([
                'title' => 'Updated Title',
                'amount' => '200.00',
            ]);
    }

    public function test_user_cannot_update_completed_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::COMPLETED,
        ]);

        $updateData = ['title' => 'Updated Title'];

        $response = $this->actingAs($this->user)
            ->putJson("/api/merchant/orders/{$order->id}", $updateData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_user_can_cancel_pending_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/merchant/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CANCELLED,
        ]);
    }

    public function test_user_cannot_cancel_completed_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::COMPLETED,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/merchant/orders/{$order->id}");

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_get_orders_rules_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/merchant/orders/rules');

        $response->assertOk()
            ->assertJsonStructure([
                'max_top_up_amount',
                'max_transfer_amount',
                'required_fields',
                'allowed_statuses',
            ])
            ->assertJsonFragment([
                'max_top_up_amount' => Order::MAX_TOP_UP_AMOUNT,
                'max_transfer_amount' => Order::MAX_TRANSFER_AMOUNT,
            ]);
    }

    public function test_get_top_up_providers_endpoint(): void
    {
        TopUpProvider::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/merchant/top-up-providers');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $this->provider->id]);
    }

    public function test_unauthenticated_user_cannot_access_endpoints(): void
    {
        $this->getJson('/api/merchant/orders')->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->postJson('/api/merchant/orders', [])->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->getJson('/api/merchant/orders/rules')->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->getJson('/api/merchant/top-up-providers')->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}