<?php

namespace Tests\Browser\Merchant;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PendingTransfersSimpleTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function user_can_login_and_see_wallet_page()
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
                    ->waitFor('.card', 10)
                    ->type('email', $user->email)
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 15)
                    ->assertSee('Wallet Balance')
                    ->assertSee('$500.00');
        });
    }
}