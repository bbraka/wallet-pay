<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MerchantTransferTest extends DuskTestCase
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
     * Test transfer page displays correctly
     */
    public function test_transfer_page_displays_correctly(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 150.00
        ]);

        // Create other users for transfer
        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/transfer')
                 ->waitFor('.card-header', 10)
                 ->assertSee('Transfer Funds')
                 ->assertSee('Current Balance')
                 ->assertSee('$150.00')
                 ->assertSee('After Transfer')
                 ->assertSee('Create Transfer Order')
                 ->assertPresent('#receiver_user_id')
                 ->assertPresent('#title')
                 ->assertPresent('#amount')
                 ->assertPresent('#description');
        });
    }

    /**
     * Test successful transfer creation
     */
    public function test_successful_transfer_creation(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 200.00
        ]);

        $recipient = User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com'
        ]);

        $this->browse(function (Browser $browser) use ($user, $recipient) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/transfer')
                 ->waitFor('.card-header', 10)
                 ->select('#receiver_user_id', (string)$recipient->id)
                 ->waitFor('.bg-light', 5)
                 ->assertSee('Jane Smith (jane@example.com)')
                 ->type('#title', 'Payment for services')
                 ->type('#amount', '75.50')
                 ->type('#description', 'Monthly consulting payment')
                 ->waitFor('.card.bg-light', 5)
                 ->assertSee('Transfer Summary')
                 ->assertSee('Jane Smith')
                 ->assertSee('$75.50')
                 ->press('Send Transfer')
                 ->waitFor('.alert-success', 15)
                 ->assertSee('Transfer order created successfully!')
                 ->assertSee('Order ID: #');

            // Form should be reset
            $browser->assertSelected('#receiver_user_id', '')
                    ->assertInputValue('#title', '')
                    ->assertInputValue('#amount', '')
                    ->assertInputValue('#description', '');
        });
    }

    /**
     * Test transfer validation - insufficient balance
     */
    public function test_transfer_insufficient_balance(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 50.00
        ]);

        $recipient = User::factory()->create([
            'name' => 'Bob Wilson',
            'email' => 'bob@example.com'
        ]);

        $this->browse(function (Browser $browser) use ($user, $recipient) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/transfer')
                 ->waitFor('.card-header', 10)
                 ->select('#receiver_user_id', (string)$recipient->id)
                 ->type('#title', 'Large transfer')
                 ->type('#amount', '100.00')
                 ->waitFor('.text-danger', 5)
                 ->assertSee('Insufficient balance for this transfer')
                 ->press('Send Transfer')
                 ->waitFor('.alert-danger', 5)
                 ->assertSee('Insufficient balance for this transfer');
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
            'wallet_amount' => 100.00
        ]);

        $recipient = User::factory()->create([
            'name' => 'Alice Brown',
            'email' => 'alice@example.com'
        ]);

        $this->browse(function (Browser $browser) use ($user, $recipient) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/transfer')
                 ->waitFor('.card-header', 10)
                 ->assertSee('$100.00') // Current balance
                 ->select('#receiver_user_id', (string)$recipient->id)
                 ->type('#amount', '30.00')
                 ->waitFor('.text-success', 5)
                 ->assertSee('$70.00'); // Balance after transfer (100 - 30)
        });
    }

    /**
     * Test form validation
     */
    public function test_transfer_form_validation(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 100.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/transfer')
                 ->waitFor('.card-header', 10)
                 ->press('Send Transfer')
                 ->waitFor('.alert-danger', 5)
                 ->assertSee('Please fill in all required fields');

            // Test invalid amount
            $browser->type('#title', 'Test')
                    ->type('#amount', '0')
                    ->press('Send Transfer')
                    ->waitFor('.alert-danger', 5)
                    ->assertSee('Transfer amount must be greater than 0');
        });
    }

    /**
     * Test recipient selection and display
     */
    public function test_recipient_selection_display(): void
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 100.00
        ]);

        $recipients = User::factory()->count(3)->create();

        $this->browse(function (Browser $browser) use ($user, $recipients) {
            $this->loginUser($browser, $user)
                 ->visit('/merchant/transfer')
                 ->waitFor('.card-header', 10);

            // Check that all recipients except current user are listed
            foreach ($recipients as $recipient) {
                $browser->assertSeeIn('#receiver_user_id', $recipient->name)
                        ->assertSeeIn('#receiver_user_id', $recipient->email);
            }

            // Current user should not be in the list
            $browser->assertDontSeeIn('#receiver_user_id', $user->name);
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
                 ->visit('/merchant/transfer')
                 ->waitFor('.card-header', 10)
                 ->click('button:contains("Back")')
                 ->waitForLocation('/merchant/wallet', 10)
                 ->assertSee('Wallet Balance');
        });
    }
}