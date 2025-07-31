<?php

namespace Tests\Browser\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Support\Facades\Hash;

class TransactionsCrudTest extends DuskTestCase
{
    use DatabaseMigrations;
    protected User $adminUser;
    protected User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password')
        ]);
        $this->adminUser->assignRole('admin');
        
        $this->targetUser = User::factory()->create([
            'email' => 'user@test.com',
            'wallet_amount' => 100.00
        ]);
        
        // Create initial transaction to match wallet balance
        Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'type' => TransactionType::CREDIT,
            'amount' => 100.00,
            'status' => TransactionStatus::ACTIVE,
            'created_by' => $this->adminUser->id,
            'description' => 'Initial balance'
        ]);
    }

    public function test_admin_can_view_transactions_list()
    {
        // Create some test transactions
        Transaction::factory()->count(3)->create([
            'user_id' => $this->targetUser->id,
            'created_by' => $this->adminUser->id
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction')
                    ->assertPathIs('/admin/transaction')
                    ->assertSee('Transactions')
                    ->waitFor('#crudTable', 10)
                    ->assertSee($this->targetUser->email); // Should see user email in table
        });
    }

    public function test_admin_can_access_create_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction/create')
                    ->assertPathIs('/admin/transaction/create')
                    ->assertSee('Add Transaction')
                    ->assertSee('User')
                    ->assertSee('Type')
                    ->assertSee('Amount')
                    ->assertSee('Description');
        });
    }

    public function test_admin_can_create_credit_transaction()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction/create')
                    ->select('user_id', $this->targetUser->id)
                    ->select('type', TransactionType::CREDIT->value)
                    ->type('amount', '25.00')
                    ->type('description', 'Test credit transaction')
                    ->press('Save')
                    ->waitForLocation('/admin/transaction')
                    ->assertSee('successfully created');
        });
    }

    public function test_admin_can_create_debit_transaction_with_sufficient_balance()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction/create')
                    ->select('user_id', $this->targetUser->id)
                    ->select('type', TransactionType::DEBIT->value)
                    ->type('amount', '30.00')
                    ->type('description', 'Test debit transaction')
                    ->press('Save')
                    ->waitForLocation('/admin/transaction')
                    ->assertSee('successfully created');
        });
    }

    public function test_admin_can_view_transaction_details()
    {
        // Create a test transaction
        $transaction = Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'type' => TransactionType::CREDIT,
            'amount' => 50.00,
            'status' => TransactionStatus::ACTIVE,
            'description' => 'Test transaction details',
            'created_by' => $this->adminUser->id
        ]);

        $this->browse(function (Browser $browser) use ($transaction) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit("/admin/transaction/{$transaction->id}/show")
                    ->assertPathIs("/admin/transaction/{$transaction->id}/show")
                    ->assertSee('Test transaction details')
                    ->assertSee('50.00')
                    ->assertSee($this->targetUser->email)
                    ->assertSee($this->adminUser->email); // Created by
        });
    }

    public function test_admin_can_filter_transactions_by_type()
    {
        // Create transactions with different types
        Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'type' => TransactionType::CREDIT,
            'created_by' => $this->adminUser->id
        ]);
        Transaction::factory()->create([
            'user_id' => $this->targetUser->id,
            'type' => TransactionType::DEBIT,
            'amount' => 25.00, // Smaller debit amount
            'created_by' => $this->adminUser->id
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/transaction')
                    ->assertPathIs('/admin/transaction')
                    ->waitFor('#crudTable', 10)
                    ->clickLink('Filters')
                    ->waitFor('[name="type"]')
                    ->select('type', TransactionType::CREDIT->value)
                    ->press('Apply filters')
                    ->waitUntilMissing('.dataTables_processing', 15)
                    ->assertSee('CREDIT');
        });
    }
}