<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MerchantLoginTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that accessing root redirects to merchant login
     */
    public function test_root_redirects_to_merchant_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->waitForLocation('/merchant/login', 10)
                    ->assertUrlIs($this->baseUrl() . '/merchant/login')
                    ->assertPresent('#merchant-app');
        });
    }

    /**
     * Test that unauthenticated users are redirected to login
     */
    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant')
                    ->waitFor('.card', 10)
                    ->assertSee('User Wallet')
                    ->assertPresent('input[type="email"]')
                    ->assertPresent('input[type="password"]')
                    ->assertPresent('button[type="submit"]');
        });
    }

    /**
     * Test successful login flow
     */
    public function test_successful_login_flow(): void
    {
        // Create a test user
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 100.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/merchant/login')
                    ->waitFor('.card', 10)
                    ->type('email', $user->email)
                    ->type('password', 'password123')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 15)
                    ->assertUrlIs($this->baseUrl() . '/merchant/wallet')
                    ->waitFor('.wallet-balance', 10)
                    ->assertSee('Wallet Balance')
                    ->assertSee('$100.00')
                    ->assertPresent('.header-user-info')
                    ->assertSee($user->name);
        });
    }

    /**
     * Test login with invalid credentials
     */
    public function test_login_with_invalid_credentials(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->waitFor('.card', 10)
                    ->type('email', 'invalid@example.com')
                    ->type('password', 'wrongpassword')
                    ->press('Sign In')
                    ->waitFor('.alert-danger', 10)
                    ->assertSee('The provided credentials are incorrect')
                    ->assertUrlIs($this->baseUrl() . '/merchant/login');
        });
    }

    /**
     * Test login form validation
     */
    public function test_login_form_validation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->waitFor('.card', 10)
                    ->press('Sign In')
                    ->waitFor('.text-danger', 5)
                    ->assertSee('Email is required')
                    ->assertSee('Password is required');
        });
    }

    /**
     * Test logout functionality
     */
    public function test_logout_functionality(): void
    {
        // Create and authenticate a user
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 50.00
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            // Login first
            $browser->visit('/merchant/login')
                    ->waitFor('.card', 10)
                    ->type('email', $user->email)
                    ->type('password', 'password123')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 15)
                    ->assertSee('Wallet Balance');

            // Test logout
            $browser->click('.dropdown-toggle')
                    ->waitFor('.dropdown-menu', 5)
                    ->click('a[href*="logout"]')
                    ->waitForLocation('/merchant/login', 10)
                    ->assertSee('User Wallet');
        });
    }

    /**
     * Test direct access to protected routes redirects to login
     */
    public function test_protected_routes_require_authentication(): void
    {
        $routes = ['/merchant/wallet', '/merchant/top-up', '/merchant/transfer', '/merchant/withdrawal'];

        $this->browse(function (Browser $browser) use ($routes) {
            foreach ($routes as $route) {
                $browser->visit($route)
                        ->waitFor('.card', 10)
                        ->assertSee('User Wallet')
                        ->assertUrlIs($this->baseUrl() . '/merchant/login');
            }
        });
    }
}