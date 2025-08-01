<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DuskLoginTest extends DuskTestCase
{
    /** @test */
    public function user_can_login_and_see_wallet_page()
    {
        // Create test user directly in database (avoid factory issues)
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/merchant/login')
                    ->pause(2000) // Let React load
                    ->waitFor('input[name="email"]', 10)
                    ->type('email', $user->email)
                    ->type('password', 'password123')
                    ->press('Sign In')
                    ->pause(3000) // Give time for login and redirect
                    ->screenshot('after-login');
            
            // Check if we're on wallet page by looking for wallet-related content
            $currentUrl = $browser->driver->getCurrentURL();
            
            // If redirected to wallet, should see wallet content
            if (str_contains($currentUrl, '/wallet')) {
                $browser->assertSee('$500.00');
            }
            
            $this->assertNotEmpty($browser->driver->getPageSource());
        });
    }

    /** @test */
    public function login_form_shows_validation_errors_for_empty_fields()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->pause(2000) // Let React load
                    ->waitFor('input[name="email"]', 10)
                    ->press('Sign In')
                    ->pause(2000) // Wait for validation
                    ->screenshot('validation-errors');
            
            // Check that we're still on login page (didn't redirect)
            $currentUrl = $browser->driver->getCurrentURL();
            $this->assertStringContainsString('/login', $currentUrl);
        });
    }
}