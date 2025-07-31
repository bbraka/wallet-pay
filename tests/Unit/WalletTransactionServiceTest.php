<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Wallet\InsufficientBalanceException;
use App\Models\User;
use App\Services\WalletTransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WalletTransactionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private WalletTransactionService $walletService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletTransactionService::class);
        $this->user = User::factory()->create(['wallet_amount' => 0.00]);
        
        // Add initial balance through a transaction
        $this->walletService->add($this->user, 100.00, 'Initial balance');
    }

    public function test_can_add_money_to_wallet(): void
    {
        $transaction = $this->walletService->add($this->user, 50.00, 'Test credit');

        $this->assertEquals(TransactionType::CREDIT, $transaction->type);
        $this->assertEquals(50.00, $transaction->amount);
        $this->assertEquals(TransactionStatus::ACTIVE, $transaction->status);
        $this->assertEquals($this->user->id, $transaction->user_id);
        
        // Check balance was updated
        $this->user->refresh();
        $this->assertEquals(150.00, $this->user->wallet_amount);
    }

    public function test_can_withdraw_money_from_wallet(): void
    {
        $transaction = $this->walletService->withdraw($this->user, 30.00, 'Test debit');

        $this->assertEquals(TransactionType::DEBIT, $transaction->type);
        $this->assertEquals(-30.00, $transaction->amount);
        $this->assertEquals(TransactionStatus::ACTIVE, $transaction->status);
        $this->assertEquals($this->user->id, $transaction->user_id);
        
        // Check balance was updated
        $this->user->refresh();
        $this->assertEquals(70.00, $this->user->wallet_amount);
    }

    public function test_cannot_withdraw_more_than_balance(): void
    {
        $this->expectException(InsufficientBalanceException::class);
        
        $this->walletService->withdraw($this->user, 150.00, 'Overdraft attempt');
    }

    public function test_can_cancel_transaction(): void
    {
        $transaction = $this->walletService->add($this->user, 25.00, 'Test credit');
        $this->user->refresh();
        $this->assertEquals(125.00, $this->user->wallet_amount);

        $this->walletService->cancel($transaction);
        
        $this->user->refresh();
        $transaction->refresh();
        
        $this->assertEquals(TransactionStatus::CANCELLED, $transaction->status);
        $this->assertEquals(100.00, $this->user->wallet_amount); // Back to original
    }

    public function test_calculates_balance_correctly(): void
    {
        $this->walletService->add($this->user, 50.00, 'Credit 1');
        $this->walletService->withdraw($this->user, 25.00, 'Debit 1');
        $this->walletService->add($this->user, 10.00, 'Credit 2');

        $balance = $this->walletService->calculateUserBalance($this->user);
        
        $this->assertEquals(135.00, $balance); // 100 + 50 - 25 + 10
    }

    public function test_cancelled_transactions_not_included_in_balance(): void
    {
        $transaction1 = $this->walletService->add($this->user, 50.00, 'Credit 1');
        $this->walletService->add($this->user, 30.00, 'Credit 2');
        
        $this->assertEquals(180.00, $this->walletService->calculateUserBalance($this->user));
        
        $this->walletService->cancel($transaction1);
        
        $this->assertEquals(130.00, $this->walletService->calculateUserBalance($this->user));
    }

    public function test_has_sufficient_balance_check(): void
    {
        $this->assertTrue($this->walletService->hasSufficientBalance($this->user, 50.00));
        $this->assertTrue($this->walletService->hasSufficientBalance($this->user, 100.00));
        $this->assertFalse($this->walletService->hasSufficientBalance($this->user, 150.00));
    }
}
