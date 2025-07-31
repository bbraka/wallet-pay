<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class MerchantAuthenticationTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create([
            'name' => 'Test Merchant',
            'email' => 'merchant@example.com',
            'wallet_amount' => 100.00,
        ]);
    }

    /**
     * Test merchant login page loads correctly
     */
    public function testLoginPageLoads()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->assertSee('User Wallet')
                    ->assertSee('Sign in to your merchant account')
                    ->assertSee('Email Address')
                    ->assertSee('Password')
                    ->assertSee('Remember me')
                    ->assertSee('Sign In');
        });
    }

    /**
     * Test merchant login with valid credentials
     */
    public function testSuccessfulLogin()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet')
                    ->assertSee('User Wallet')
                    ->assertSee($this->user->name)
                    ->assertSee('Balance: $100.00');
        });
    }

    /**
     * Test merchant login with invalid credentials
     */
    public function testFailedLogin()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->type('email', 'invalid@example.com')
                    ->type('password', 'wrongpassword')
                    ->press('Sign In')
                    ->waitForText('The provided credentials are incorrect')
                    ->assertPathIs('/merchant/login');
        });
    }

    /**
     * Test form validation on login page
     */
    public function testLoginFormValidation()
    {
        $this->browse(function (Browser $browser) {
            // Test empty form
            $browser->visit('/merchant/login')
                    ->press('Sign In')
                    ->assertSee('Email is required')
                    ->assertSee('Password is required');

            // Test invalid email format
            $browser->type('email', 'invalid-email')
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->assertSee('Please enter a valid email address');

            // Test short password
            $browser->type('email', 'test@example.com')
                    ->type('password', '123')
                    ->press('Sign In')
                    ->assertSee('Password must be at least 6 characters');
        });
    }

    /**
     * Test merchant logout functionality
     */
    public function testLogout()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/merchant/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet');

            // Test logout
            $browser->click('[aria-expanded="false"]') // Click profile dropdown
                    ->waitForText('Sign Out')
                    ->click('Sign Out')
                    ->waitForLocation('/merchant/login')
                    ->assertSee('Sign in to your merchant account');
        });
    }

    /**
     * Test protected route redirects to login
     */
    public function testProtectedRouteRedirect()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/wallet')
                    ->waitForLocation('/merchant/login')
                    ->assertSee('Sign in to your merchant account');
        });
    }

    /**
     * Test authenticated user redirects from login page
     */
    public function testAuthenticatedUserRedirect()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/merchant/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet');

            // Try to visit login page again
            $browser->visit('/merchant/login')
                    ->waitForLocation('/merchant/wallet')
                    ->assertDontSee('Sign in to your merchant account');
        });
    }
}