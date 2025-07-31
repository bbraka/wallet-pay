<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use App\Models\Order;
use App\Models\TopUpProvider;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class WalletDashboardTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create([
            'name' => 'Test Merchant',
            'email' => 'merchant@example.com',
            'wallet_amount' => 250.75,
        ]);

        // Create test provider
        $this->provider = TopUpProvider::factory()->create([
            'name' => 'Test Bank',
            'code' => 'TEST_BANK',
            'is_active' => true,
            'requires_reference' => false,
        ]);

        // Create test orders
        Order::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'order_type' => 'user_top_up',
            'status' => 'completed',
            'top_up_provider_id' => $this->provider->id,
        ]);

        Order::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'order_type' => 'internal_transfer',
            'status' => 'pending_payment',
        ]);
    }

    /**
     * Login helper method
     */
    protected function loginUser(Browser $browser)
    {
        $browser->visit('/merchant/login')
                ->type('email', $this->user->email)
                ->type('password', 'password')
                ->press('Sign In')
                ->waitForLocation('/merchant/wallet');
    }

    /**
     * Test wallet dashboard loads correctly
     */
    public function testWalletDashboardLoads()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->assertSee('Wallet Balance')
                    ->assertSee('$250.75')
                    ->assertSee('Quick Actions')
                    ->assertSee('Top Up')
                    ->assertSee('Transfer')
                    ->assertSee('Withdraw')
                    ->assertSee('Transaction History')
                    ->assertSee('8 transactions'); // 5 + 3 orders
        });
    }

    /**
     * Test navigation to different pages via quick actions
     */
    public function testQuickActionsNavigation()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            // Test Top Up navigation
            $browser->click('button:contains("Top Up")')
                    ->waitForLocation('/merchant/top-up')
                    ->assertSee('Top Up Wallet')
                    ->back()
                    ->waitForLocation('/merchant/wallet');

            // Test Transfer navigation
            $browser->click('button:contains("Transfer")')
                    ->waitForLocation('/merchant/transfer')
                    ->assertSee('Transfer Funds')
                    ->back()
                    ->waitForLocation('/merchant/wallet');

            // Test Withdraw navigation
            $browser->click('button:contains("Withdraw")')
                    ->waitForLocation('/merchant/withdrawal')
                    ->assertSee('Withdraw Funds')
                    ->back()
                    ->waitForLocation('/merchant/wallet');
        });
    }

    /**
     * Test transaction filtering functionality
     */
    public function testTransactionFiltering()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            // Test status filter
            $browser->select('#status', 'completed')
                    ->press('Apply Filters')
                    ->waitForText('5 transactions')
                    ->assertSee('5 transactions');

            // Test reset filters
            $browser->click('button:contains("Reset")')
                    ->waitForText('8 transactions')
                    ->assertSee('8 transactions');

            // Test amount filter
            $browser->type('#min_amount', '50')
                    ->type('#max_amount', '200')
                    ->press('Apply Filters')
                    ->pause(1000); // Wait for filter results
        });
    }

    /**
     * Test transaction table displays correct information
     */
    public function testTransactionTableDisplay()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->assertSee('ID')
                    ->assertSee('Title')
                    ->assertSee('Type')
                    ->assertSee('Amount')
                    ->assertSee('Status')
                    ->assertSee('Date')
                    ->assertSee('Actions');

            // Check for order data
            $orders = Order::where('user_id', $this->user->id)->take(3)->get();
            
            foreach ($orders as $order) {
                $browser->assertSee("#{$order->id}")
                        ->assertSee($order->title)
                        ->assertSee('$' . number_format($order->amount, 2));
            }
        });
    }

    /**
     * Test header navigation and user info display
     */
    public function testHeaderNavigation()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            // Test header shows user info
            $browser->assertSee($this->user->name)
                    ->assertSee('Balance: $250.75');

            // Test navigation links
            $browser->click('a:contains("Top Up")')
                    ->waitForLocation('/merchant/top-up')
                    ->assertSee('Top Up Wallet');

            $browser->click('a:contains("Wallet")')
                    ->waitForLocation('/merchant/wallet')
                    ->assertSee('Wallet Balance');
        });
    }

    /**
     * Test responsive design elements
     */
    public function testResponsiveDesign()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            // Test on mobile viewport
            $browser->resize(375, 667)
                    ->assertSee('Wallet Balance')
                    ->assertSee('Quick Actions')
                    ->assertVisible('.navbar-toggler'); // Bootstrap mobile menu button

            // Test on desktop viewport
            $browser->resize(1200, 800)
                    ->assertSee('Wallet Balance')
                    ->assertNotVisible('.navbar-toggler'); // Menu should be expanded
        });
    }

    /**
     * Test refresh functionality
     */
    public function testRefreshFunctionality()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->click('button:contains("Refresh")')
                    ->pause(2000) // Wait for API call
                    ->assertSee('Transaction History');
        });
    }
}