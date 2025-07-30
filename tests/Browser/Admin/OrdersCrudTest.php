<?php

namespace Tests\Browser\Admin;

use App\Models\TopUpProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class OrdersCrudTest extends DuskTestCase
{
    use RefreshDatabase;
    protected User $adminUser;
    protected User $targetUser;
    protected TopUpProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
        
        // Create admin role and permission
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        $adminPermission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access admin area']);
        $adminRole->givePermissionTo($adminPermission);
        $this->adminUser->assignRole($adminRole);
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
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order')
                    ->assertSee('Orders')
                    ->assertSee('Add order');
        });
    }

    public function test_admin_can_create_top_up_order()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order/create')
                    ->assertSee('Add order')
                    ->type('title', 'Test Admin Top-up')
                    ->type('amount', '150.00')
                    ->select('user_id', $this->targetUser->id)
                    ->select('top_up_provider_id', $this->provider->id)
                    ->type('description', 'Test description')
                    ->press('Save')
                    ->assertPathIs('/admin/order')
                    ->assertSee('Test Admin Top-up');
        });
        
        // Verify the order was created
        $this->assertDatabaseHas('orders', [
            'title' => 'Test Admin Top-up',
            'amount' => 150.00,
            'user_id' => $this->targetUser->id,
            'order_type' => 'admin_top_up'
        ]);
    }

    public function test_form_validation_shows_errors()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order/create')
                    ->press('Save')
                    ->assertSee('The title field is required')
                    ->assertSee('The amount field is required')
                    ->assertSee('The user id field is required');
        });
    }

    public function test_user_search_functionality()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order/create')
                    ->click('[name="user_id"] + .select2')
                    ->type('.select2-search__field', 'user@test')
                    ->waitFor('.select2-results__option')
                    ->assertSee($this->targetUser->email);
        });
    }

    public function test_filters_work_correctly()
    {
        // Create test orders
        $oldOrder = \App\Models\Order::factory()->create([
            'title' => 'Old Order',
            'created_at' => now()->subDays(5)
        ]);
        
        $newOrder = \App\Models\Order::factory()->create([
            'title' => 'New Order',
            'created_at' => now()
        ]);

        $this->browse(function (Browser $browser) use ($oldOrder, $newOrder) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order')
                    ->assertSee($oldOrder->title)
                    ->assertSee($newOrder->title)
                    
                    // Apply date filter
                    ->click('#filter_date_range')
                    ->type('[name="date_range_start"]', now()->format('Y-m-d'))
                    ->type('[name="date_range_end"]', now()->format('Y-m-d'))
                    ->press('Apply filters')
                    
                    ->assertSee($newOrder->title)
                    ->assertDontSee($oldOrder->title);
        });
    }

    public function test_order_details_page()
    {
        $order = \App\Models\Order::factory()->create([
            'title' => 'Test Order Details',
            'amount' => 75.50,
            'user_id' => $this->targetUser->id
        ]);

        $this->browse(function (Browser $browser) use ($order) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit("/admin/order/{$order->id}/show")
                    ->assertSee('Test Order Details')
                    ->assertSee('$75.50')
                    ->assertSee($this->targetUser->email);
        });
    }

    public function test_user_wallet_updates_after_top_up()
    {
        $initialBalance = $this->targetUser->wallet_amount;

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order/create')
                    ->type('title', 'Wallet Test Top-up')
                    ->type('amount', '50.00')
                    ->select('user_id', $this->targetUser->id)
                    ->select('top_up_provider_id', $this->provider->id)
                    ->press('Save')
                    ->assertPathIs('/admin/order');
        });

        // Verify wallet was updated
        $this->targetUser->refresh();
        $this->assertEquals($initialBalance + 50.00, $this->targetUser->wallet_amount);
    }

    public function test_clickable_user_links_work()
    {
        $order = \App\Models\Order::factory()->create([
            'user_id' => $this->targetUser->id
        ]);

        $this->browse(function (Browser $browser) use ($order) {
            $browser->loginAs($this->adminUser, 'backpack')
                    ->visit('/admin/order')
                    ->clickLink($this->targetUser->name)
                    ->assertPathBeginsWith('/admin/user/')
                    ->assertSee($this->targetUser->email);
        });
    }
}