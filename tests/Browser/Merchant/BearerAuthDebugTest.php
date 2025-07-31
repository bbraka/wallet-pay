<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BearerAuthDebugTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->artisan('db:seed');
    }

    /**
     * Debug login process to see what's happening
     */
    public function test_debug_login_process()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->screenshot('01-login-page')
                    ->type('email', 'test@example.com')
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->pause(3000) // Wait for login process
                    ->screenshot('02-after-login')
                    ;

            // Check current URL
            $currentUrl = $browser->driver->getCurrentURL();
            echo "Current URL: " . $currentUrl . "\n";

            // Check localStorage
            $tokenExists = $browser->script('return localStorage.getItem("auth_token")')[0];
            echo "Token in localStorage: " . ($tokenExists ? $tokenExists : 'null') . "\n";

            // Check API response manually
            $apiResponse = $browser->script('
                return fetch("/api/merchant/login", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: JSON.stringify({
                        email: "test@example.com",
                        password: "password"
                    })
                }).then(response => response.json()).then(data => JSON.stringify(data));
            ')[0];
            echo "API Response: " . $apiResponse . "\n";

            // Check if token is being stored after login
            $tokenAfterLogin = $browser->script('
                // Simulate what the login process should do
                const token = "test-token-123";
                localStorage.setItem("auth_token", token);
                return localStorage.getItem("auth_token");
            ')[0];
            echo "Manual token storage test: " . $tokenAfterLogin . "\n";

            // Check console logs for errors
            $logs = $browser->driver->manage()->getLog('browser');
            echo "Console logs: " . json_encode($logs) . "\n";

            // Check page source
            $pageSource = $browser->driver->getPageSource();
            echo "Page contains 'Wallet Balance': " . (strpos($pageSource, 'Wallet Balance') !== false ? 'YES' : 'NO') . "\n";
            echo "Page contains 'Sign In': " . (strpos($pageSource, 'Sign In') !== false ? 'YES' : 'NO') . "\n";
            echo "Page contains error: " . (strpos($pageSource, 'error') !== false ? 'YES' : 'NO') . "\n";
        });
    }
}