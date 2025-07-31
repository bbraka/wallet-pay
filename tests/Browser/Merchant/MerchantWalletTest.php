<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use App\Models\Order;
use App\Models\TopUpProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MerchantWalletTest extends DuskTestCase
{
    use DatabaseMigrations;

    private function loginUser(Browser $browser, User $user): Browser
    {
        return $browser->visit('/merchant/login')
                      ->waitFor('.login-container', 10)
                      ->type('email', $user->email)
                      ->type('password', 'password123')
                      ->press('Login')
                      ->waitForLocation('/merchant/wallet', 15);
    }

    /**
     * Test wallet dashboard displays correctly
     */
    public function test_wallet_dashboard_displays_correctly(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 250.75
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->assertSee('Wallet Balance')
                 ->assertSee('$250.75')
                 ->assertSee('Quick Actions')
                 ->assertSee('Top Up')
                 ->assertSee('Transfer')
                 ->assertSee('Withdraw')
                 ->assertSee('Refresh')
                 ->assertSee('Transaction History');
        });
    }

    /**
     * Test transaction filters functionality
     */
    public function test_transaction_filters_work(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 100.00
        ]);

        // Create test orders
        Order::factory()->create([
            'user_id' => $user->id,
            'title' => 'Test Top-up',
            'amount' => 50.00,
            'status' => 'completed',
            'order_type' => 'user_top_up'
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'title' => 'Test Transfer',
            'amount' => 25.00,
            'status' => 'pending_payment',
            'order_type' => 'internal_transfer'
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->waitFor('.table', 10)
                 ->assertSee('Test Top-up')
                 ->assertSee('Test Transfer');

            // Test status filter
            $browser->select('#status', 'completed')
                    ->press('Apply Filters')
                    ->waitFor('.table', 5)
                    ->assertSee('Test Top-up')
                    ->assertDontSee('Test Transfer');

            // Reset filters
            $browser->press('Reset')
                    ->waitFor('.table', 5)
                    ->assertSee('Test Top-up')
                    ->assertSee('Test Transfer');
        });
    }

    /**
     * Test quick action buttons navigation
     */
    public function test_quick_action_buttons_navigation(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 100.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user);

            // Test Top Up button
            $browser->click('button:contains("Top Up")')
                    ->waitForLocation('/merchant/top-up', 10)
                    ->assertSee('Top Up Wallet')
                    ->back()
                    ->waitForLocation('/merchant/wallet', 10);

            // Test Transfer button
            $browser->click('button:contains("Transfer")')
                    ->waitForLocation('/merchant/transfer', 10)
                    ->assertSee('Transfer Funds')
                    ->back()
                    ->waitForLocation('/merchant/wallet', 10);

            // Test Withdraw button
            $browser->click('button:contains("Withdraw")')
                    ->waitForLocation('/merchant/withdrawal', 10)
                    ->assertSee('Withdraw Funds')
                    ->back()
                    ->waitForLocation('/merchant/wallet', 10);
        });
    }

    /**
     * Test empty transaction state
     */
    public function test_empty_transaction_state(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 0.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->assertSee('No transactions found')
                 ->assertSee('Start by making your first transaction');
        });
    }

    /**
     * Test refresh functionality
     */
    public function test_refresh_functionality(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 100.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->click('button:contains("Refresh")')
                 ->waitFor('.spinner-border', 2)
                 ->waitUntilMissing('.spinner-border', 10)
                 ->assertSee('Transaction History');
        });
    }
}