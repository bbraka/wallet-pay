<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DebugTokenTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_debug_token_storage(): void
    {
        // Debug database configuration
        echo "Test Environment: " . app()->environment() . "\n";
        echo "App URL: " . config('app.url') . "\n";
        echo "Database Connection: " . config('database.default') . "\n";
        echo "Database Name: " . config('database.connections.mysql.database') . "\n";
        echo "Test Database Name: " . config('database.connections.mysql_testing.database') . "\n";
        
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant')
                    ->waitFor('#merchant-app', 10)
                    ->waitFor('input[name="email"]', 10)
                    ->type('email', 'test@example.com')
                    ->type('password', 'password')
                    ->click('button[type="submit"]')
                    ->pause(3000); // Wait for login to complete
                    
            // Add some JavaScript to check what happened
            $browser->script('console.log("After login - checking localStorage:", localStorage.getItem("auth_token"));');
            
            // Check console logs
            $logs = $browser->driver->manage()->getLog('browser');
            foreach ($logs as $log) {
                echo "Console: " . $log['message'] . "\n";
            }
            
            // Verify token is stored
            $token = $browser->script('return localStorage.getItem("auth_token");')[0];
            echo "Token from localStorage: " . ($token ?? 'null') . "\n";
        });
    }
}
