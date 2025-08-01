<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderConfirmationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;
    private User $receiver;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->sender = User::factory()->create([
            'wallet_amount' => 1000.00
        ]);
        
        $this->receiver = User::factory()->create([
            'wallet_amount' => 500.00
        ]);
        
        // Create some initial transactions to properly set up the wallet balances
        \App\Models\Transaction::create([
            'user_id' => $this->sender->id,
            'type' => \App\Enums\TransactionType::CREDIT,
            'amount' => 1000.00,
            'status' => \App\Enums\TransactionStatus::ACTIVE,
            'description' => 'Initial balance',
            'created_by' => $this->sender->id,
        ]);
        
        \App\Models\Transaction::create([
            'user_id' => $this->receiver->id,
            'type' => \App\Enums\TransactionType::CREDIT,
            'amount' => 500.00,
            'status' => \App\Enums\TransactionStatus::ACTIVE,
            'description' => 'Initial balance',
            'created_by' => $this->receiver->id,
        ]);
    }

    public function test_can_get_pending_transfers_for_authenticated_user()
    {
        // Authenticate as receiver
        $token = $this->authenticateUser($this->receiver);

        // Create pending transfers
        $pendingTransfer1 = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer 1'
        ]);

        $pendingTransfer2 = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_APPROVAL,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 200.00,
            'title' => 'Test Transfer 2'
        ]);

        // Make request
        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/merchant/orders/pending-transfers');

        // Assert response
        $response->assertOk();
        $response->assertJsonCount(2);
        
        $response->assertJsonStructure([
            '*' => [
                'id',
                'title',
                'amount',
                'status',
                'order_type',
                'user',
                'receiver',
                'created_at'
            ]
        ]);

        // Assert specific transfers are included
        $responseData = $response->json();
        $transferIds = collect($responseData)->pluck('id')->toArray();
        $this->assertContains($pendingTransfer1->id, $transferIds);
        $this->assertContains($pendingTransfer2->id, $transferIds);
    }

    public function test_returns_empty_array_when_no_pending_transfers_exist()
    {
        // Authenticate as receiver
        $token = $this->authenticateUser($this->receiver);

        // Make request
        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/merchant/orders/pending-transfers');

        // Assert response
        $response->assertOk();
        $response->assertJsonCount(0);
    }

    public function test_can_confirm_a_pending_transfer()
    {
        // Authenticate as receiver
        $token = $this->authenticateUser($this->receiver);

        // Create a pending transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer'
        ]);

        // Make request
        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/merchant/orders/{$order->id}/confirm");

        // Assert response
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $order->id,
            'status' => OrderStatus::COMPLETED->value
        ]);

        // Assert database was updated
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::COMPLETED->value
        ]);
    }

    public function test_can_reject_a_pending_transfer()
    {
        // Authenticate as receiver
        $token = $this->authenticateUser($this->receiver);

        // Create a pending transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer'
        ]);

        // Make request
        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/merchant/orders/{$order->id}/reject");

        // Assert response
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $order->id,
            'status' => OrderStatus::CANCELLED->value
        ]);

        // Assert database was updated
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CANCELLED->value
        ]);
    }

    public function test_cannot_confirm_order_not_meant_for_authenticated_user()
    {
        // Authenticate as sender (not receiver)
        $token = $this->authenticateUser($this->sender);

        // Create a pending transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer'
        ]);

        // Make request
        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/merchant/orders/{$order->id}/confirm");

        // Assert forbidden response
        $response->assertForbidden();

        // Assert database was not updated
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PENDING_PAYMENT->value
        ]);
    }

    public function test_cannot_reject_order_not_meant_for_authenticated_user()
    {
        // Authenticate as sender (not receiver)
        $token = $this->authenticateUser($this->sender);

        // Create a pending transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer'
        ]);

        // Make request
        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/merchant/orders/{$order->id}/reject");

        // Assert forbidden response
        $response->assertForbidden();

        // Assert database was not updated
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PENDING_PAYMENT->value
        ]);
    }

    public function test_cannot_confirm_already_completed_order()
    {
        // Authenticate as receiver
        $token = $this->authenticateUser($this->receiver);

        // Create a completed transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::COMPLETED,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer'
        ]);

        // Make request
        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/merchant/orders/{$order->id}/confirm");

        // Assert validation error
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['order']);
    }

    public function test_cannot_reject_already_completed_order()
    {
        // Authenticate as receiver
        $token = $this->authenticateUser($this->receiver);

        // Create a completed transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::COMPLETED,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer'
        ]);

        // Make request
        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/merchant/orders/{$order->id}/reject");

        // Assert validation error
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['order']);
    }

    public function test_requires_authentication_for_pending_transfers()
    {
        // Make request without authentication
        $response = $this->getJson('/api/merchant/orders/pending-transfers');

        // Assert unauthenticated response
        $response->assertUnauthorized();
    }

    public function test_requires_authentication_for_confirm_order()
    {
        // Create a pending transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer'
        ]);

        // Make request without authentication
        $response = $this->postJson("/api/merchant/orders/{$order->id}/confirm");

        // Assert unauthenticated response
        $response->assertUnauthorized();
    }

    public function test_requires_authentication_for_reject_order()
    {
        // Create a pending transfer
        $order = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'Test Transfer'
        ]);

        // Make request without authentication
        $response = $this->postJson("/api/merchant/orders/{$order->id}/reject");

        // Assert unauthenticated response
        $response->assertUnauthorized();
    }

    public function test_returns_404_for_nonexistent_order()
    {
        // Authenticate as receiver
        $token = $this->authenticateUser($this->receiver);

        // Make request with non-existent order ID
        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/merchant/orders/99999/confirm");

        // Assert not found response
        $response->assertNotFound();
    }

    private function authenticateUser(User $user): string
    {
        $token = Str::random(60);
        $user->update(['api_token' => hash('sha256', $token)]);
        return $token;
    }

    private function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }
}