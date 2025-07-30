<?php

namespace Tests\Feature\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCrudControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
        
        // Create admin role and permission
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        $adminPermission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access admin area']);
        $adminRole->givePermissionTo($adminPermission);
        $this->adminUser->assignRole($adminRole);
        $this->targetUser = User::factory()->create([
            'email' => 'user@test.com',
            'wallet_amount' => 0.00
        ]);
        
        // Create an initial transaction to give the user a 100.00 balance
        \App\Models\Transaction::create([
            'user_id' => $this->adminUser->id, // Temp user_id
            'type' => \App\Enums\TransactionType::CREDIT,
            'amount' => 100.00,
            'status' => \App\Enums\TransactionStatus::ACTIVE,
            'description' => 'Initial balance',
            'created_by' => $this->adminUser->id,
        ]);
        // Update to correct user_id after creation to avoid observer triggering during setup
        \App\Models\Transaction::where('description', 'Initial balance')->update(['user_id' => $this->targetUser->id]);
        $this->targetUser->update(['wallet_amount' => 100.00]); // Set the initial balance manually
    }

    public function test_admin_can_create_credit_transaction()
    {
        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'credit',
            'amount' => 50.00,
            'description' => 'Manual credit',
        ];
        
        $transaction = $transactionService->createManualTransaction($data, $this->adminUser);
        
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->targetUser->id,
            'type' => 'credit',
            'amount' => 50.00,
            'created_by' => $this->adminUser->id,
            'status' => 'active'
        ]);
    }

    public function test_admin_can_create_debit_transaction()
    {
        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'debit',
            'amount' => 30.00,
            'description' => 'Manual debit'
        ];
        
        $transaction = $transactionService->createManualTransaction($data, $this->adminUser);
        
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->targetUser->id,
            'type' => 'debit',
            'amount' => -30.00
        ]);
    }

    public function test_validates_debit_amount_exceeds_balance()
    {
        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'debit',
            'amount' => 150.00, // Exceeds user's balance
            'description' => 'Excessive debit'
        ];
        
        $this->expectException(\Exception::class);
        $transactionService->createManualTransaction($data, $this->adminUser);
        
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $this->targetUser->id,
            'amount' => -150.00
        ]);
    }

    public function test_validates_required_fields()
    {
        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        $data = [
            // Missing required fields
        ];
        
        $this->expectException(\Exception::class);
        $transactionService->createManualTransaction($data, $this->adminUser);
    }

    public function test_validates_amount_minimum()
    {
        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'credit',
            'amount' => 0
        ];
        
        $this->expectException(\Exception::class);
        $transactionService->createManualTransaction($data, $this->adminUser);
    }

    public function test_admin_can_update_manual_transaction()
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'type' => TransactionType::CREDIT,
            'amount' => 50.00,
            'created_by' => $this->adminUser->id
        ]);

        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        $data = [
            'amount' => 75.00,
            'description' => 'Updated transaction'
        ];
        
        $updatedTransaction = $transactionService->updateTransaction($transaction, $data);
        
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 75.00,
            'description' => 'Updated transaction'
        ]);
    }

    public function test_cannot_update_system_transaction()
    {
        $transaction = Transaction::factory()->system()->create([
            'user_id' => $this->targetUser->id,
        ]);

        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        $data = [
            'amount' => 100.00,
            'description' => 'Should not be allowed'
        ];
        
        $this->expectException(\Exception::class);
        $transactionService->updateTransaction($transaction, $data);
    }

    public function test_admin_can_delete_manual_transaction()
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'created_by' => $this->adminUser->id,
            'order_id' => null
        ]);

        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        // Check if can be deleted first
        $this->assertTrue($transactionService->canDeleteTransaction($transaction));
        
        // Delete the transaction
        $transaction->delete();
        
        $this->assertDatabaseMissing('transactions', [
            'id' => $transaction->id
        ]);
    }

    public function test_cannot_delete_system_transaction()
    {
        $transaction = Transaction::factory()->system()->create();

        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        // Check that system transactions cannot be deleted
        $this->assertFalse($transactionService->canDeleteTransaction($transaction));
        
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id
        ]);
    }

    public function test_check_balance_endpoint_works()
    {
        $this->actingAs($this->adminUser, 'backpack');
        
        $response = $this->post('/admin/transaction/check-balance', [
            'user_id' => $this->targetUser->id,
            'amount' => 50.00
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'sufficient' => true,
            'balance' => 100.00
        ]);
    }

    public function test_check_balance_returns_insufficient_when_exceeded()
    {
        $this->actingAs($this->adminUser, 'backpack');
        
        $response = $this->post('/admin/transaction/check-balance', [
            'user_id' => $this->targetUser->id,
            'amount' => 150.00
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'sufficient' => false,
            'balance' => 100.00
        ]);
    }

    public function test_updates_user_wallet_after_credit()
    {
        $initialBalance = $this->targetUser->wallet_amount;
        
        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'credit',
            'amount' => 50.00,
            'description' => 'Credit test'
        ];
        
        $transactionService->createManualTransaction($data, $this->adminUser);
        
        $this->targetUser->refresh();
        $this->assertEquals($initialBalance + 50.00, $this->targetUser->wallet_amount);
    }

    public function test_updates_user_wallet_after_debit()
    {
        $initialBalance = $this->targetUser->wallet_amount;
        
        $transactionService = app(\App\Services\Admin\TransactionService::class);
        
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'debit',
            'amount' => 30.00,
            'description' => 'Debit test'
        ];
        
        $transactionService->createManualTransaction($data, $this->adminUser);
        
        $this->targetUser->refresh();
        $this->assertEquals($initialBalance - 30.00, $this->targetUser->wallet_amount);
    }
}