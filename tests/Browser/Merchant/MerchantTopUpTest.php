<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use App\Models\TopUpProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MerchantTopUpTest extends DuskTestCase
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

    public function setUp(): void
    {
        parent::setUp();
        
        // Create test top-up providers
        TopUpProvider::factory()->create([
            'name' => 'Bank Transfer',
            'code' => 'bank_transfer',
            'description' => 'Direct bank transfer',
            'is_active' => true,
            'requires_reference' => true
        ]);

        TopUpProvider::factory()->create([
            'name' => 'Credit Card',
            'code' => 'credit_card',
            'description' => 'Credit card payment',
            'is_active' => true,
            'requires_reference' => false
        ]);
    }

    /**
     * Test top-up page displays correctly
     */
    public function test_topup_page_displays_correctly(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 50.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/top-up')
                 ->waitFor('.card-header', 10)
                 ->assertSee('Top Up Wallet')
                 ->assertSee('Current Balance')
                 ->assertSee('$50.00')
                 ->assertSee('Create Top-up Order')
                 ->assertPresent('#title')
                 ->assertPresent('#amount')
                 ->assertPresent('#top_up_provider_id')
                 ->assertPresent('#description');
        });
    }

    /**
     * Test successful top-up creation
     */
    public function test_successful_topup_creation(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 50.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/top-up')
                 ->waitFor('.card-header', 10)
                 ->type('#title', 'Test Wallet Top-up')
                 ->type('#amount', '100.50')
                 ->select('#top_up_provider_id', '2') // Credit Card (no reference required)
                 ->type('#description', 'Test top-up transaction')
                 ->press('Create Top-up Order')
                 ->waitFor('.alert-success', 15)
                 ->assertSee('Top-up order created successfully!')
                 ->assertSee('Order ID: #');

            // Form should be reset after successful submission
            $browser->assertInputValue('#title', '')
                    ->assertInputValue('#amount', '')
                    ->assertSelected('#top_up_provider_id', '')
                    ->assertInputValue('#description', '');
        });
    }

    /**
     * Test top-up with provider reference requirement
     */
    public function test_topup_with_provider_reference(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 25.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/top-up')
                 ->waitFor('.card-header', 10)
                 ->type('#title', 'Bank Transfer Top-up')
                 ->type('#amount', '75.00')
                 ->select('#top_up_provider_id', '1') // Bank Transfer (requires reference)
                 ->waitFor('#provider_reference', 5)
                 ->assertSee('Payment Reference')
                 ->assertSee('This payment method requires a reference number')
                 ->type('#provider_reference', 'TXN123456789')
                 ->press('Create Top-up Order')
                 ->waitFor('.alert-success', 15)
                 ->assertSee('Top-up order created successfully!');
        });
    }

    /**
     * Test form validation
     */
    public function test_topup_form_validation(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 25.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/top-up')
                 ->waitFor('.card-header', 10)
                 ->press('Create Top-up Order')
                 ->waitFor('.alert-danger', 5)
                 ->assertSee('Please fill in all required fields');

            // Test invalid amount
            $browser->type('#title', 'Test')
                    ->type('#amount', '0')
                    ->select('#top_up_provider_id', '2')
                    ->press('Create Top-up Order')
                    ->waitFor('.alert-danger', 5)
                    ->assertSee('Amount must be greater than 0');

            // Test missing reference for bank transfer
            $browser->type('#amount', '50.00')
                    ->select('#top_up_provider_id', '1') // Bank Transfer
                    ->waitFor('#provider_reference', 5)
                    ->press('Create Top-up Order')
                    ->waitFor('.alert-danger', 5)
                    ->assertSee('Provider reference is required');
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
                 ->visit('/merchant/top-up')
                 ->waitFor('.card-header', 10)
                 ->click('button:contains("Back")')
                 ->waitForLocation('/merchant/wallet', 10)
                 ->assertSee('Wallet Balance');
        });
    }

    /**
     * Test provider loading states
     */
    public function test_provider_loading_states(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 100.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/top-up')
                 ->waitFor('#top_up_provider_id option[value="1"]', 10)
                 ->assertSeeIn('#top_up_provider_id', 'Bank Transfer')
                 ->assertSeeIn('#top_up_provider_id', 'Credit Card');
        });
    }
}