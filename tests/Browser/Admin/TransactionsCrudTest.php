<?php

namespace Tests\Browser\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TransactionsCrudTest extends DuskTestCase
{
    use RefreshDatabase;
    protected User $adminUser;
    protected User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
        
        // Create admin role and permission
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        $adminPermission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access admin area']);
        $adminRole->givePermissionTo($adminPermission);
        $this->adminUser->assignRole($adminRole);
        $this->targetUser = User::factory()->create([
            'email' => 'user@test.com',
            'wallet_amount' => 100.00
        ]);
    }

    public function test_admin_can_view_transactions_list()
    {
        $this->browse(function (Browser $browser) {
            // Login manually through the login form
            $browser->visit('/admin/login')
                    ->type('email', $this->adminUser->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->visit('/admin/transaction')
                    ->waitForText('Transaction', 10) // Wait for Backpack to load
                    ->assertSee('Transaction');
        });
    }

    public function test_admin_can_create_credit_transaction()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction/create')
                    ->assertSee('Add transaction')
                    ->select('user_id', $this->targetUser->id)
                    ->select('type', 'credit')
                    ->type('amount', '50.00')
                    ->type('description', 'Test credit transaction')
                    ->press('Save')
                    ->assertPathIs('/admin/transaction')
                    ->assertSee('Transaction created successfully');
        });
        
        // Verify the transaction was created
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->targetUser->id,
            'type' => 'credit',
            'amount' => 50.00,
            'created_by' => $this->adminUser->id
        ]);
    }

    public function test_admin_can_create_debit_transaction()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction/create')
                    ->select('user_id', $this->targetUser->id)
                    ->select('type', 'debit')
                    ->type('amount', '30.00')
                    ->type('description', 'Test debit transaction')
                    ->press('Save')
                    ->assertPathIs('/admin/transaction')
                    ->assertSee('Transaction created successfully');
        });
        
        // Verify the transaction was created
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->targetUser->id,
            'type' => 'debit',
            'amount' => 30.00
        ]);
    }

    public function test_balance_validation_prevents_excessive_debit()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction/create')
                    ->select('user_id', $this->targetUser->id)
                    ->select('type', 'debit')
                    ->type('amount', '150.00') // Exceeds balance
                    ->pause(1000) // Wait for AJAX balance check
                    ->assertSee('Insufficient balance');
        });
    }

    public function test_form_validation_shows_errors()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction/create')
                    ->press('Save')
                    ->assertSee('Please select a user')
                    ->assertSee('Please select a transaction type')
                    ->assertSee('Amount is required');
        });
    }

    public function test_amount_auto_converts_to_positive()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction/create')
                    ->type('amount', '-50.00')
                    ->assertInputValue('amount', '50'); // Should convert to positive
        });
    }

    public function test_filters_work_correctly()
    {
        // Create test transactions
        $creditTransaction = Transaction::factory()->create([
            'type' => TransactionType::CREDIT,
            'amount' => 50.00,
            'description' => 'Credit Test'
        ]);
        
        $debitTransaction = Transaction::factory()->create([
            'type' => TransactionType::DEBIT,
            'amount' => 25.00,
            'description' => 'Debit Test'
        ]);

        $this->browse(function (Browser $browser) use ($creditTransaction, $debitTransaction) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction')
                    ->assertSee('Credit Test')
                    ->assertSee('Debit Test')
                    
                    // Apply type filter
                    ->select('#filter_type', 'credit')
                    ->press('Apply filters')
                    
                    ->assertSee('Credit Test')
                    ->assertDontSee('Debit Test');
        });
    }

    public function test_manual_transaction_shows_edit_delete_buttons()
    {
        $manualTransaction = Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'created_by' => $this->adminUser->id,
            'order_id' => null,
            'description' => 'Manual Transaction'
        ]);

        $this->browse(function (Browser $browser) use ($manualTransaction) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction')
                    ->assertSee('Manual Transaction')
                    ->assertSeeIn("tr[data-entry-id='{$manualTransaction->id}']", 'Edit')
                    ->assertSeeIn("tr[data-entry-id='{$manualTransaction->id}']", 'Delete');
        });
    }

    public function test_system_transaction_shows_only_show_button()
    {
        $systemTransaction = Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'created_by' => null,
            'order_id' => 1,
            'description' => 'System Transaction'
        ]);

        $this->browse(function (Browser $browser) use ($systemTransaction) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction')
                    ->assertSee('System Transaction')
                    ->assertSeeIn("tr[data-entry-id='{$systemTransaction->id}']", 'Show')
                    ->assertDontSeeIn("tr[data-entry-id='{$systemTransaction->id}']", 'Edit')
                    ->assertDontSeeIn("tr[data-entry-id='{$systemTransaction->id}']", 'Delete');
        });
    }

    public function test_admin_can_edit_manual_transaction()
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'type' => TransactionType::CREDIT,
            'amount' => 50.00,
            'created_by' => $this->adminUser->id,
            'description' => 'Original Description'
        ]);

        $this->browse(function (Browser $browser) use ($transaction) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit("/admin/transaction/{$transaction->id}/edit")
                    ->assertInputValue('amount', '50')
                    ->clear('amount')
                    ->type('amount', '75.00')
                    ->clear('description')
                    ->type('description', 'Updated Description')
                    ->press('Update')
                    ->assertPathIs('/admin/transaction')
                    ->assertSee('Transaction updated successfully');
        });
        
        // Verify the transaction was updated
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => 75.00,
            'description' => 'Updated Description'
        ]);
    }

    public function test_transaction_details_page()
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'type' => TransactionType::CREDIT,
            'amount' => 75.50,
            'description' => 'Test Transaction Details'
        ]);

        $this->browse(function (Browser $browser) use ($transaction) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit("/admin/transaction/{$transaction->id}/show")
                    ->assertSee('Test Transaction Details')
                    ->assertSee('$75.50')
                    ->assertSee($this->targetUser->email);
        });
    }

    public function test_user_wallet_updates_after_transaction()
    {
        $initialBalance = $this->targetUser->wallet_amount;

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction/create')
                    ->select('user_id', $this->targetUser->id)
                    ->select('type', 'credit')
                    ->type('amount', '25.00')
                    ->type('description', 'Wallet update test')
                    ->press('Save')
                    ->assertPathIs('/admin/transaction');
        });

        // Verify wallet was updated
        $this->targetUser->refresh();
        $this->assertEquals($initialBalance + 25.00, $this->targetUser->wallet_amount);
    }

    public function test_clickable_user_links_work()
    {
        $transaction = Transaction::factory()->create([
            'user_id' => $this->targetUser->id
        ]);

        $this->browse(function (Browser $browser) use ($transaction) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction')
                    ->clickLink($this->targetUser->name)
                    ->assertPathBeginsWith('/admin/user/')
                    ->assertSee($this->targetUser->email);
        });
    }

    public function test_date_range_filter()
    {
        // Create transactions on different dates
        $oldTransaction = Transaction::factory()->create([
            'description' => 'Old Transaction',
            'created_at' => now()->subDays(10)
        ]);
        
        $newTransaction = Transaction::factory()->create([
            'description' => 'New Transaction',
            'created_at' => now()
        ]);

        $this->browse(function (Browser $browser) use ($oldTransaction, $newTransaction) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction')
                    ->assertSee('Old Transaction')
                    ->assertSee('New Transaction')
                    
                    // Apply date range filter
                    ->click('#filter_date_range')
                    ->type('[name="date_range_start"]', now()->format('Y-m-d'))
                    ->type('[name="date_range_end"]', now()->format('Y-m-d'))
                    ->press('Apply filters')
                    
                    ->assertSee('New Transaction')
                    ->assertDontSee('Old Transaction');
        });
    }
}