<?php

namespace Tests\Browser\Admin;

use App\Models\Order;
use App\Models\TopUpProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Support\Facades\Hash;

class OrdersCrudTest extends DuskTestCase
{
    use DatabaseMigrations;
    protected User $adminUser;
    protected User $targetUser;
    protected TopUpProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password')
        ]);
        $this->adminUser->assignRole('admin');
        
        $this->targetUser = User::factory()->create([
            'email' => 'user@test.com',
            'wallet_amount' => 100.00
        ]);
        
        $this->provider = TopUpProvider::factory()->create([
            'name' => 'Test Provider',
            'code' => 'TEST',
            'is_active' => true
        ]);
    }

    public function test_admin_can_view_orders_list()
    {
        // Create some test orders
        Order::factory()->count(3)->create([
            'user_id' => $this->targetUser->id,
            'top_up_provider_id' => $this->provider->id
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order')
                    ->assertPathIs('/admin/order')
                    ->assertSee('Orders')
                    ->waitFor('#crudTable', 10)
                    ->assertSee($this->targetUser->email); // Should see user email in table
        });
    }

    public function test_admin_can_access_create_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order/create')
                    ->assertPathIs('/admin/order/create')
                    ->waitFor('form', 10) // Wait for form to load
                    ->assertSee('User')
                    ->assertSee('Top-up Provider')
                    ->assertSee('Amount')
                    ->assertSee('Provider Reference')
                    ->assertDontSee('Title'); // Title field should be removed
        });
    }

    public function test_admin_can_create_order()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order/create')
                    ->waitFor('form', 10)
                    ->select('user_id', $this->targetUser->id)
                    ->select('top_up_provider_id', $this->provider->id)
                    ->type('amount', '50.00')
                    ->type('description', 'Test admin top-up')
                    ->type('provider_reference', 'REF123')
                    ->press('Save')
                    ->pause(3000) // Wait for processing
                    ->assertPathBeginsWith('/admin/order'); // More flexible path check
        });
    }

    public function test_admin_can_view_order_details()
    {
        // Create a test order with auto-generated title format
        $order = Order::factory()->create([
            'title' => 'Admin Top-up - ' . $this->targetUser->email . ' - $75.50',
            'amount' => 75.50,
            'user_id' => $this->targetUser->id,
            'top_up_provider_id' => $this->provider->id,
            'order_type' => 'admin_top_up'
        ]);

        $this->browse(function (Browser $browser) use ($order) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit("/admin/order/{$order->id}/show")
                    ->assertPathIs("/admin/order/{$order->id}/show")
                    ->assertSee('Admin Top-up') // Should see auto-generated title
                    ->assertSee('75.50')
                    ->assertSee($this->targetUser->email);
        });
    }

    public function test_admin_can_filter_orders()
    {
        // Create orders with different statuses
        Order::factory()->create([
            'status' => 'pending_payment',
            'user_id' => $this->targetUser->id,
            'top_up_provider_id' => $this->provider->id
        ]);
        Order::factory()->create([
            'status' => 'completed',
            'user_id' => $this->targetUser->id,
            'top_up_provider_id' => $this->provider->id
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order')
                    ->assertPathIs('/admin/order')
                    ->waitFor('#crudTable', 10)
                    ->clickLink('Filters')
                    ->waitFor('[name="status"]')
                    ->select('status', 'pending_payment')
                    ->press('Apply filters')
                    ->waitUntilMissing('.dataTables_processing', 15)
                    ->assertSee('pending_payment');
        });
    }
}