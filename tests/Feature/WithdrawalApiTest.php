<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Transaction;
use App\Enums\TransactionType;
use App\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class WithdrawalApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'wallet_amount' => 0.00
        ]);
        
        // Create a transaction to give the user a 1000.00 balance
        Transaction::create([
            'user_id' => $this->user->id,
            'type' => TransactionType::CREDIT,
            'amount' => 1000.00,
            'status' => TransactionStatus::ACTIVE,
            'description' => 'Initial test balance',
            'created_by' => $this->user->id,
        ]);
        
        // Update the wallet_amount to match the transaction
        $this->user->update(['wallet_amount' => 1000.00]);
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

    /** @test */
    public function it_can_create_withdrawal_with_valid_data()
    {
        $token = $this->authenticateUser($this->user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/merchant/orders/withdrawal', [
                'amount' => 100.00,
                'description' => 'Test withdrawal'
            ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'id',
                     'amount',
                     'status',
                     'order_type',
                     'description'
                 ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'amount' => 100.00,
            'order_type' => 'user_withdrawal',
            'description' => 'Test withdrawal'
        ]);
    }

    /** @test */
    public function it_requires_amount_field()
    {
        $token = $this->authenticateUser($this->user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/merchant/orders/withdrawal', [
                'description' => 'Test withdrawal'
            ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_validates_amount_is_numeric()
    {
        $token = $this->authenticateUser($this->user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/merchant/orders/withdrawal', [
                'amount' => 'not-a-number',
                'description' => 'Test withdrawal'
            ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_validates_amount_is_positive()
    {
        $token = $this->authenticateUser($this->user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/merchant/orders/withdrawal', [
                'amount' => -50.00,
                'description' => 'Test withdrawal'
            ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_validates_amount_does_not_exceed_balance()
    {
        $token = $this->authenticateUser($this->user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/merchant/orders/withdrawal', [
                'amount' => 1500.00, // More than $1000 balance
                'description' => 'Test withdrawal'
            ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_accepts_optional_description()
    {
        $token = $this->authenticateUser($this->user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/merchant/orders/withdrawal', [
                'amount' => 50.00
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'amount' => 50.00,
            'order_type' => 'user_withdrawal'
        ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->postJson('/api/merchant/orders/withdrawal', [
            'amount' => 100.00,
            'description' => 'Test withdrawal'
        ]);

        $response->assertStatus(401);
    }
}