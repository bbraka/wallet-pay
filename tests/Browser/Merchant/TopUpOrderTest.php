<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use App\Models\TopUpProvider;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class TopUpOrderTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create([
            'name' => 'Test Merchant',
            'email' => 'merchant@example.com',
            'wallet_amount' => 50.00,
        ]);

        // Create test providers
        $this->provider = TopUpProvider::factory()->create([
            'name' => 'Bank Transfer',
            'code' => 'BANK_TRANSFER',
            'description' => 'Standard bank transfer',
            'is_active' => true,
            'requires_reference' => false,
        ]);

        $this->providerWithReference = TopUpProvider::factory()->create([
            'name' => 'Credit Card',
            'code' => 'CREDIT_CARD',
            'description' => 'Credit card payment',
            'is_active' => true,
            'requires_reference' => true,
        ]);

        // Create inactive provider
        TopUpProvider::factory()->create([
            'name' => 'Inactive Provider',
            'code' => 'INACTIVE',
            'is_active' => false,
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
     * Test top-up page loads correctly
     */
    public function testTopUpPageLoads()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->visit('/merchant/top-up')
                    ->assertSee('Top Up Wallet')
                    ->assertSee('Add funds to your wallet')
                    ->assertSee('Current Balance')
                    ->assertSee('$50.00')
                    ->assertSee('Create Top-up Order')
                    ->assertSee('Title')
                    ->assertSee('Amount')
                    ->assertSee('Payment Method')
                    ->assertSee('Important Information');
        });
    }

    /**
     * Test top-up form validation
     */
    public function testTopUpFormValidation()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->visit('/merchant/top-up')
                    ->press('Create Top-up Order')
                    ->waitForText('Please fill in all required fields')
                    ->assertSee('Please fill in all required fields');

            // Test invalid amount
            $browser->type('#title', 'Test Top-up')
                    ->type('#amount', '0')
                    ->select('#top_up_provider_id', $this->provider->id)
                    ->press('Create Top-up Order')
                    ->waitForText('Amount must be greater than 0')
                    ->assertSee('Amount must be greater than 0');

            // Test negative amount
            $browser->type('#amount', '-10')
                    ->press('Create Top-up Order')
                    ->waitForText('Amount must be greater than 0')
                    ->assertSee('Amount must be greater than 0');
        });
    }

    /**
     * Test successful top-up order creation
     */
    public function testSuccessfulTopUpOrder()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->visit('/merchant/top-up')
                    ->type('#title', 'Wallet Top-up via Bank Transfer')
                    ->type('#amount', '100.50')
                    ->select('#top_up_provider_id', $this->provider->id)
                    ->type('#description', 'Adding funds for project expenses')
                    ->press('Create Top-up Order')
                    ->waitForText('Top-up order created successfully!')
                    ->assertSee('Top-up order created successfully!')
                    ->assertSee('Order ID: #');

            // Check form is reset after successful submission
            $browser->assertInputValue('#title', '')
                    ->assertInputValue('#amount', '')
                    ->assertInputValue('#description', '')
                    ->assertSelected('#top_up_provider_id', '');
        });
    }

    /**
     * Test provider with reference requirement
     */
    public function testProviderWithReference()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->visit('/merchant/top-up')
                    ->select('#top_up_provider_id', $this->providerWithReference->id)
                    ->waitForText('Payment Reference')
                    ->assertSee('Payment Reference')
                    ->assertSee('This payment method requires a reference number for verification');

            // Test validation when reference is required but not provided
            $browser->type('#title', 'Credit Card Top-up')
                    ->type('#amount', '75.25')
                    ->press('Create Top-up Order')
                    ->waitForText('Provider reference is required for this payment method')
                    ->assertSee('Provider reference is required for this payment method');

            // Test successful submission with reference
            $browser->type('#provider_reference', 'TXN123456789')
                    ->press('Create Top-up Order')
                    ->waitForText('Top-up order created successfully!')
                    ->assertSee('Top-up order created successfully!');
        });
    }

    /**
     * Test only active providers are shown
     */
    public function testOnlyActiveProvidersShown()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->visit('/merchant/top-up')
                    ->click('#top_up_provider_id')
                    ->assertSee('Bank Transfer')
                    ->assertSee('Credit Card')
                    ->assertDontSee('Inactive Provider');
        });
    }

    /**
     * Test navigation back to wallet
     */
    public function testNavigationBackToWallet()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->visit('/merchant/top-up')
                    ->click('button:contains("Back")')
                    ->waitForLocation('/merchant/wallet')
                    ->assertSee('Wallet Balance');

            // Test back arrow button
            $browser->visit('/merchant/top-up')
                    ->click('.fa-arrow-left')
                    ->waitForLocation('/merchant/wallet')
                    ->assertSee('Wallet Balance');
        });
    }

    /**
     * Test form field interactions
     */
    public function testFormFieldInteractions()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->visit('/merchant/top-up')
                    ->type('#title', 'Test Top-up')
                    ->type('#amount', '150.75')
                    ->select('#top_up_provider_id', $this->provider->id)
                    ->type('#description', 'Test description for top-up order')
                    ->assertInputValue('#title', 'Test Top-up')
                    ->assertInputValue('#amount', '150.75')
                    ->assertSelected('#top_up_provider_id', $this->provider->id)
                    ->assertInputValue('#description', 'Test description for top-up order');
        });
    }

    /**
     * Test loading states
     */
    public function testLoadingStates()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            $browser->visit('/merchant/top-up')
                    ->waitUntilMissing('.spinner-border') // Wait for providers to load
                    ->assertDontSee('Loading payment providers...')
                    ->assertSee('Create Top-up Order');
        });
    }

    /**
     * Test responsive design on top-up page
     */
    public function testTopUpPageResponsive()
    {
        $this->browse(function (Browser $browser) {
            $this->loginUser($browser);
            
            // Test mobile view
            $browser->resize(375, 667)
                    ->visit('/merchant/top-up')
                    ->assertSee('Top Up Wallet')
                    ->assertSee('Current Balance');

            // Test desktop view
            $browser->resize(1200, 800)
                    ->assertSee('Top Up Wallet')
                    ->assertSee('Current Balance');
        });
    }
}