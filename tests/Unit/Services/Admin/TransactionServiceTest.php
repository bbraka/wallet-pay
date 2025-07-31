<?php

namespace Tests\Unit\Services\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionService $transactionService;
    protected User $adminUser;
    protected User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->transactionService = app(TransactionService::class);
        
        $this->adminUser = User::factory()->create();
        $this->targetUser = User::factory()->create([
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

    public function test_creates_credit_transaction_successfully()
    {
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'credit',
            'amount' => 50.00,
            'description' => 'Manual credit'
        ];

        $transaction = $this->transactionService->createManualTransaction($data, $this->adminUser);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($this->targetUser->id, $transaction->user_id);
        $this->assertEquals(TransactionType::CREDIT, $transaction->type);
        $this->assertEquals(50.00, $transaction->amount);
        $this->assertEquals($this->adminUser->id, $transaction->created_by);
        $this->assertEquals(TransactionStatus::ACTIVE, $transaction->status);
    }

    public function test_creates_debit_transaction_successfully()
    {
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'debit',
            'amount' => 30.00,
            'description' => 'Manual debit'
        ];

        $transaction = $this->transactionService->createManualTransaction($data, $this->adminUser);

        $this->assertEquals(TransactionType::DEBIT, $transaction->type);
        $this->assertEquals(-30.00, $transaction->amount); // Negative for debit
    }

    public function test_validates_user_balance_for_debit()
    {
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'debit',
            'amount' => 150.00, // More than user's balance
            'description' => 'Excessive debit'
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Insufficient balance');
        
        $this->transactionService->createManualTransaction($data, $this->adminUser);
    }

    public function test_does_not_validate_balance_for_credit()
    {
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'credit',
            'amount' => 1000.00, // Large amount should be fine for credit
            'description' => 'Large credit'
        ];

        $transaction = $this->transactionService->createManualTransaction($data, $this->adminUser);
        
        $this->assertEquals(1000.00, $transaction->amount);
    }

    public function test_updates_user_wallet_for_credit()
    {
        $initialBalance = $this->targetUser->wallet_amount;
        
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'credit',
            'amount' => 50.00,
            'description' => 'Credit test'
        ];

        $this->transactionService->createManualTransaction($data, $this->adminUser);

        $this->targetUser->refresh();
        $this->assertEquals($initialBalance + 50.00, $this->targetUser->wallet_amount);
    }

    public function test_updates_user_wallet_for_debit()
    {
        $initialBalance = $this->targetUser->wallet_amount;
        
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'debit',
            'amount' => 30.00,
            'description' => 'Debit test'
        ];

        $this->transactionService->createManualTransaction($data, $this->adminUser);

        $this->targetUser->refresh();
        $this->assertEquals($initialBalance - 30.00, $this->targetUser->wallet_amount);
    }

    public function test_updates_transaction_amount_only()
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'type' => TransactionType::CREDIT,
            'amount' => 50.00,
            'created_by' => $this->adminUser->id
        ]);

        $data = [
            'amount' => 75.00,
            'description' => 'Updated description'
        ];

        $updatedTransaction = $this->transactionService->updateTransaction($transaction, $data);

        $this->assertEquals(75.00, $updatedTransaction->amount);
        $this->assertEquals('Updated description', $updatedTransaction->description);
        $this->assertEquals(TransactionType::CREDIT, $updatedTransaction->type); // Unchanged
    }

    public function test_recalculates_wallet_on_update()
    {
        $initialBalance = $this->targetUser->wallet_amount; // 100.00 from setup
        
        // Create additional transaction 
        $transaction = \App\Models\Transaction::create([
            'user_id' => $this->targetUser->id,
            'type' => TransactionType::CREDIT,
            'amount' => 50.00,
            'created_by' => $this->adminUser->id,
            'status' => TransactionStatus::ACTIVE,
            'description' => 'Test transaction'
        ]);
        
        // After creation, balance should be recalculated to initial + new transaction
        $this->targetUser->refresh();
        $balanceAfterCreate = $this->targetUser->wallet_amount;
        $this->assertEquals($initialBalance + 50.00, $balanceAfterCreate);

        // Update transaction amount from 50 to 75
        $data = ['amount' => 75.00];
        $this->transactionService->updateTransaction($transaction, $data);

        // Wallet should be recalculated based on all transactions
        $this->targetUser->refresh();
        $expectedBalance = $initialBalance + 75.00; // Initial balance + updated transaction
        $this->assertEquals($expectedBalance, $this->targetUser->wallet_amount);
    }

    public function test_validates_debit_balance_on_update()
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'type' => TransactionType::DEBIT,
            'amount' => 30.00,
            'created_by' => $this->adminUser->id
        ]);

        $data = ['amount' => 200.00]; // Would exceed balance

        $this->expectException(ValidationException::class);
        $this->transactionService->updateTransaction($transaction, $data);
    }

    public function test_can_delete_manual_transaction()
    {
        $transaction = Transaction::factory()->create([
            'created_by' => $this->adminUser->id,
            'order_id' => null
        ]);

        $canDelete = $this->transactionService->canDeleteTransaction($transaction);
        
        $this->assertTrue($canDelete);
    }

    public function test_cannot_delete_system_transaction()
    {
        // Create an order first to have a valid foreign key
        $order = \App\Models\Order::factory()->create();
        
        $transaction = Transaction::factory()->create([
            'created_by' => $this->adminUser->id,
            'order_id' => $order->id
        ]);

        $canDelete = $this->transactionService->canDeleteTransaction($transaction);
        
        $this->assertFalse($canDelete);
    }

    public function test_cannot_delete_order_related_transaction()
    {
        // Create an order first to have a valid foreign key
        $order = \App\Models\Order::factory()->create();
        
        $transaction = Transaction::factory()->create([
            'created_by' => $this->adminUser->id,
            'order_id' => $order->id // Has order relation
        ]);

        $canDelete = $this->transactionService->canDeleteTransaction($transaction);
        
        $this->assertFalse($canDelete);
    }

    public function test_validates_required_data()
    {
        $data = [
            // Missing required fields
        ];

        $this->expectException(ValidationException::class);
        $this->transactionService->createManualTransaction($data, $this->adminUser);
    }

    public function test_validates_amount_minimum()
    {
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'credit',
            'amount' => 0,
        ];

        $this->expectException(ValidationException::class);
        $this->transactionService->createManualTransaction($data, $this->adminUser);
    }

    public function test_validates_transaction_type()
    {
        $data = [
            'user_id' => $this->targetUser->id,
            'type' => 'invalid_type',
            'amount' => 50.00,
        ];

        $this->expectException(ValidationException::class);
        $this->transactionService->createManualTransaction($data, $this->adminUser);
    }
}