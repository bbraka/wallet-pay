<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PendingTransfersDebugTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function debug_login_flow()
    {
        // Create merchant role
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'merchant']);
        
        // Create test user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'wallet_amount' => 500.00
        ]);

        // Assign merchant role
        $user->assignRole('merchant');

        $this->browse(function (Browser $browser) use ($user) {
            // Login
            $browser->visit('/merchant/login')
                    ->screenshot('debug-login-page')
                    ->waitFor('.card', 10)
                    ->type('email', $user->email)
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->screenshot('debug-after-sign-in')
                    ->pause(5000) // Give time for React to load and render
                    ->waitFor('#merchant-app', 10) // Wait for React app div
                    ->screenshot('debug-final-page')
                    ->dump(); // This will output the current page HTML
        });
    }
}