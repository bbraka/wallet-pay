<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BearerAuthenticationTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->artisan('db:seed');
    }

    /**
     * Test that login page loads correctly
     */
    public function test_login_page_loads()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->assertSee('Sign In')
                    ->assertSee('Email Address')
                    ->assertSee('Password')
                    ->assertPresent('input[name="email"]')
                    ->assertPresent('input[name="password"]')
                    ->assertPresent('button[type="submit"]');
        });
    }

    /**
     * Test successful login with bearer token authentication
     */
    public function test_successful_login_with_bearer_token()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->type('email', 'test@example.com')
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 10)
                    ->assertPathIs('/merchant/wallet')
                    ->assertSee('Wallet Balance')
                    ->assertDontSee('Sign In');

            // Check that token is stored in localStorage
            $tokenExists = $browser->script('return localStorage.getItem("auth_token") !== null')[0];
            $this->assertTrue($tokenExists, 'Bearer token should be stored in localStorage');
        });
    }

    /**
     * Test session persistence across React pages
     */
    public function test_session_persistence_across_pages()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/merchant/login')
                    ->type('email', 'test@example.com')
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 10);

            // Navigate to different pages and ensure authentication persists
            $browser->visit('/merchant/top-up')
                    ->waitForText('Top Up Wallet', 10)
                    ->assertSee('Top Up Wallet')
                    ->assertDontSee('Sign In');

            $browser->visit('/merchant/transfer')
                    ->waitForText('Transfer Funds', 10) 
                    ->assertSee('Transfer Funds')
                    ->assertDontSee('Sign In');

            $browser->visit('/merchant/withdrawal')
                    ->waitForText('Withdraw Funds', 10)
                    ->assertSee('Withdraw Funds')
                    ->assertDontSee('Sign In');

            // Go back to wallet dashboard
            $browser->visit('/merchant/wallet')
                    ->waitForText('Wallet Balance', 10)
                    ->assertSee('Wallet Balance')
                    ->assertSee('Test User');

            // Verify token still exists after navigation
            $tokenExists = $browser->script('return localStorage.getItem("auth_token") !== null')[0];
            $this->assertTrue($tokenExists, 'Bearer token should persist across page navigation');
        });
    }

    /**
     * Test browser refresh maintains authentication
     */
    public function test_browser_refresh_maintains_authentication()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/merchant/login')
                    ->type('email', 'test@example.com')
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 10);

            // Refresh the page
            $browser->refresh()
                    ->waitForText('Wallet Balance', 10)
                    ->assertSee('Wallet Balance')
                    ->assertSee('Test User')
                    ->assertDontSee('Sign In');

            // Verify token still exists after refresh
            $tokenExists = $browser->script('return localStorage.getItem("auth_token") !== null')[0];
            $this->assertTrue($tokenExists, 'Bearer token should persist after browser refresh');
        });
    }

    /**
     * Test logout removes bearer token and redirects to login
     */
    public function test_logout_removes_token_and_redirects()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/merchant/login')
                    ->type('email', 'test@example.com')
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 10);

            // Logout
            $browser->click('.dropdown-toggle')
                    ->waitFor('.dropdown-menu.show', 5)
                    ->click('button.dropdown-item')
                    ->waitForLocation('/merchant/login', 10)
                    ->assertPathIs('/merchant/login')
                    ->assertSee('Sign In');

            // Verify token is removed from localStorage
            $tokenExists = $browser->script('return localStorage.getItem("auth_token") !== null')[0];
            $this->assertFalse($tokenExists, 'Bearer token should be removed after logout');
        });
    }

    /**
     * Test protected routes redirect to login when no token
     */
    public function test_protected_routes_redirect_without_token()
    {
        $this->browse(function (Browser $browser) {
            // Clear any existing tokens
            $browser->visit('/merchant/login')
                    ->script('localStorage.removeItem("auth_token")');

            // Try to access protected routes
            $protectedRoutes = [
                '/merchant/wallet',
                '/merchant/top-up',
                '/merchant/transfer',
                '/merchant/withdrawal'
            ];

            foreach ($protectedRoutes as $route) {
                $browser->visit($route)
                        ->waitForLocation('/merchant/login', 10)
                        ->assertPathIs('/merchant/login')
                        ->assertSee('Sign In');
            }
        });
    }

    /**
     * Test failed login shows error message
     */
    public function test_failed_login_shows_error()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->type('email', 'invalid@example.com')
                    ->type('password', 'wrongpassword')
                    ->press('Sign In')
                    ->waitForText('The provided credentials are incorrect', 10)
                    ->assertSee('The provided credentials are incorrect')
                    ->assertPathIs('/merchant/login');

            // Verify no token is stored
            $tokenExists = $browser->script('return localStorage.getItem("auth_token") !== null')[0];
            $this->assertFalse($tokenExists, 'No token should be stored after failed login');
        });
    }

    /**
     * Test API requests include bearer token after login
     */
    public function test_api_requests_include_bearer_token()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/merchant/login')
                    ->type('email', 'test@example.com')
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 10);

            // Navigate to a page that makes API requests
            $browser->visit('/merchant/top-up')
                    ->waitForText('Top Up Wallet', 10);

            // Check that the token is being used for API requests
            // This is verified by checking that the page loads successfully
            // (if token wasn't sent, we'd get 401 errors and be redirected)
            $browser->assertSee('Top Up Wallet')
                    ->assertDontSee('Sign In')
                    ->assertDontSee('401')
                    ->assertDontSee('Unauthorized');
        });
    }

    /**
     * Test login form validation
     */
    public function test_login_form_validation()
    {
        $this->browse(function (Browser $browser) {
            // Test empty form
            $browser->visit('/merchant/login')
                    ->press('Sign In')
                    ->waitForText('Email is required', 5)
                    ->assertSee('Email is required')
                    ->assertSee('Password is required');

            // Test invalid email format
            $browser->type('email', 'invalid-email')
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForText('Please enter a valid email address', 5)
                    ->assertSee('Please enter a valid email address');
        });
    }
}