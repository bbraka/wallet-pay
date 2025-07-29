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
            $browser->visit('/admin/login')
                    ->type('email', $admin->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->assertUrlIs('/admin/dashboard')
                    ->assertSee('Dashboard');
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

        $this->browse(function (Browser $browser) use ($merchant) {
            $browser->visit('/admin/login')
                    ->type('email', $merchant->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->assertUrlIs('/admin/login')
                    ->assertSee('Unauthorized');
        });
    }

    /**
     * Test admin can access user management
     */
    public function testAdminCanAccessUserManagement(): void
    {
        // Create admin user
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password')
        ]);
        $admin->assignRole('admin');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/dashboard')
                    ->assertSee('User Management')
                    ->clickLink('Users')
                    ->assertUrlIs('/admin/user')
                    ->assertSee('Users');
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
            $browser->loginAs($admin)
                    ->visit('/admin/dashboard')
                    ->clickLink('Roles')
                    ->assertUrlIs('/admin/role')
                    ->assertSee('Roles');
        });
    }

    /**
     * Test admin can access permission management
     */
    public function testAdminCanAccessPermissionManagement(): void
    {
        // Create admin user
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password')
        ]);
        $admin->assignRole('admin');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/dashboard')
                    ->clickLink('Permissions')
                    ->assertUrlIs('/admin/permission')
                    ->assertSee('Permissions');
        });
    }
}
