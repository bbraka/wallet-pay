<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MerchantWithdrawalTest extends DuskTestCase
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
     * Test withdrawal page displays correctly
     */
    public function test_withdrawal_page_displays_correctly(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 200.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/withdrawal')
                 ->waitFor('.card-header', 10)
                 ->assertSee('Withdraw Funds')
                 ->assertSee('Available Balance')
                 ->assertSee('$200.00')
                 ->assertSee('After Withdrawal')
                 ->assertSee('Create Withdrawal Request')
                 ->assertSee('Quick Amounts')
                 ->assertPresent('#amount')
                 ->assertPresent('#description');
        });
    }

    /**
     * Test successful withdrawal creation
     */
    public function test_successful_withdrawal_creation(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 500.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/withdrawal')
                 ->waitFor('.card-header', 10)
                 ->type('#amount', '150.50')
                 ->type('#description', 'Emergency withdrawal for medical expenses')
                 ->waitFor('.card.bg-light', 5)
                 ->assertSee('Withdrawal Summary')
                 ->assertSee('Current Balance: $500.00')
                 ->assertSee('Withdrawal Amount: -$150.50')
                 ->assertSee('Remaining Balance: $349.50')
                 ->press('Submit Withdrawal Request')
                 ->waitFor('.alert-success', 15)
                 ->assertSee('Withdrawal request created successfully!')
                 ->assertSee('Order ID: #');

            // Form should be reset
            $browser->assertInputValue('#amount', '')
                    ->assertInputValue('#description', '');
        });
    }

    /**
     * Test quick amount buttons
     */
    public function test_quick_amount_buttons(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 300.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/withdrawal')
                 ->waitFor('.card-header', 10)
                 ->assertSee('Quick Amounts')
                 ->click('button:contains("$50")')
                 ->assertInputValue('#amount', '50')
                 ->click('button:contains("$100")')
                 ->assertInputValue('#amount', '100')
                 ->click('button:contains("All")')
                 ->assertInputValue('#amount', '300');
        });
    }

    /**
     * Test withdrawal with insufficient balance
     */
    public function test_withdrawal_insufficient_balance(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 25.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/withdrawal')
                 ->waitFor('.card-header', 10)
                 ->type('#amount', '50.00')
                 ->waitFor('.text-danger', 5)
                 ->assertSee('Insufficient balance for this withdrawal')
                 ->press('Submit Withdrawal Request')
                 ->waitFor('.alert-danger', 5)
                 ->assertSee('Insufficient balance for this withdrawal');
        });
    }

    /**
     * Test zero balance state
     */
    public function test_zero_balance_state(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 0.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/withdrawal')
                 ->waitFor('.card-header', 10)
                 ->assertSee('Insufficient Balance')
                 ->assertSee('You need to add funds to your wallet')
                 ->assertSee('Add Funds')
                 ->click('button:contains("Add Funds")')
                 ->waitForLocation('/merchant/top-up', 10)
                 ->assertSee('Top Up Wallet');
        });
    }

    /**
     * Test balance calculation updates
     */
    public function test_balance_calculation_updates(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 150.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/withdrawal')
                 ->waitFor('.card-header', 10)
                 ->assertSee('$150.00') // Available balance
                 ->type('#amount', '75.00')
                 ->waitFor('.text-success', 5)
                 ->assertSee('$75.00'); // Remaining balance after withdrawal
        });
    }

    /**
     * Test form validation
     */
    public function test_withdrawal_form_validation(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 100.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/withdrawal')
                 ->waitFor('.card-header', 10)
                 ->press('Submit Withdrawal Request')
                 ->waitFor('.alert-danger', 5)
                 ->assertSee('Please enter withdrawal amount');

            // Test invalid amount
            $browser->type('#amount', '0')
                    ->press('Submit Withdrawal Request')
                    ->waitFor('.alert-danger', 5)
                    ->assertSee('Withdrawal amount must be greater than 0');
        });
    }

    /**
     * Test back navigation
     */
    public function test_back_navigation(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 100.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/withdrawal')
                 ->waitFor('.card-header', 10)
                 ->click('button:contains("Back")')
                 ->waitForLocation('/merchant/wallet', 10)
                 ->assertSee('Wallet Balance');
        });
    }

    /**
     * Test withdrawal summary display
     */
    public function test_withdrawal_summary_display(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 250.75
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/withdrawal')
                 ->waitFor('.card-header', 10)
                 ->type('#amount', '100.25')
                 ->waitFor('.card.bg-light', 5)
                 ->assertSee('Withdrawal Summary')
                 ->assertSee('Current Balance: $250.75')
                 ->assertSee('Withdrawal Amount: -$100.25')
                 ->assertSee('Remaining Balance: $150.50');
        });
    }
}