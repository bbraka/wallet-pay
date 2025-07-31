<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminRbacTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    /**
     * Test admin user can access admin area
     */
    public function testAdminCanAccessAdminArea(): void
    {
        // Create admin user
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password')
        ]);
        $admin->assignRole('admin');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin, 'backpack')
                    ->visit('/admin/dashboard')
                    ->assertPathIs('/admin/dashboard');
        });
    }

    /**
     * Test merchant user cannot access admin area
     */
    public function testMerchantCannotAccessAdminArea(): void
    {
        // Create merchant user  
        $merchant = User::factory()->create([
            'email' => 'merchant@test.com',
            'password' => Hash::make('password')
        ]);
        $merchant->assignRole('merchant');

        // Simply test that merchant cannot be logged in as backpack user
        $this->browse(function (Browser $browser) use ($merchant) {
            $browser->loginAs($merchant, 'backpack')
                    ->visit('/admin/dashboard')
                    ->assertPathIs('/admin/login'); // Should redirect to login
        });
    }

    /**
     * Test admin can access role management
     */
    public function testAdminCanAccessRoleManagement(): void
    {
        // Create admin user
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password')
        ]);
        $admin->assignRole('admin');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin, 'backpack')
                    ->visit('/admin/role')
                    ->assertPathIs('/admin/role'); // Verify we reach the role page
        });
    }
}
