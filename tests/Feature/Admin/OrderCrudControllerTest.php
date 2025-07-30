<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\TopUpProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCrudControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $targetUser;
    protected TopUpProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
        
        // Create admin role and permission
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        $adminPermission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access admin area']);
        $adminRole->givePermissionTo($adminPermission);
        $this->adminUser->assignRole($adminRole);
        $this->targetUser = User::factory()->create([
            'email' => 'user@test.com', 
            'wallet_amount' => 0.00
        ]);
        
        // Create initial transaction to give user 50.00 balance
        \App\Models\Transaction::create([
            'user_id' => $this->targetUser->id,
            'type' => \App\Enums\TransactionType::CREDIT,
            'amount' => 50.00,
            'status' => \App\Enums\TransactionStatus::ACTIVE,
            'description' => 'Initial balance for testing',
            'created_by' => null, // System transaction
        ]);
        
        // Update wallet amount after transaction
        $this->targetUser->update(['wallet_amount' => 50.00]);
        
        $this->provider = TopUpProvider::factory()->create([
            'name' => 'Test Provider',
            'code' => 'TEST',
            'is_active' => true
        ]);
    }

    public function test_admin_can_create_top_up_order()
    {
        $ordersService = app(\App\Services\Merchant\OrdersService::class);
        
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'description' => 'Manual top-up',
            'top_up_provider_id' => $this->provider->id
        ];
        
        $order = $ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);
        
        $this->assertDatabaseHas('orders', [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'user_id' => $this->targetUser->id,
            'order_type' => 'admin_top_up',
            'status' => 'completed'
        ]);
    }

    public function test_validates_required_fields_on_create()
    {
        $ordersService = app(\App\Services\Merchant\OrdersService::class);
        
        $data = [
            // Missing required fields
        ];
        
        $this->expectException(\Exception::class);
        $ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);
    }

    public function test_validates_amount_minimum()
    {
        $ordersService = app(\App\Services\Merchant\OrdersService::class);
        
        $data = [
            'title' => 'Test',
            'amount' => 0,
            'top_up_provider_id' => $this->provider->id
        ];
        
        $this->expectException(\Exception::class);
        $ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);
    }

    public function test_validates_user_exists()
    {
        $ordersService = app(\App\Services\Merchant\OrdersService::class);
        $nonExistentUser = new \App\Models\User(['id' => 9999]);
        
        $data = [
            'title' => 'Test',
            'amount' => 100.00,
            'top_up_provider_id' => $this->provider->id
        ];
        
        $this->expectException(\Exception::class);
        $ordersService->createAdminTopUp($nonExistentUser, $data, $this->adminUser);
    }

    public function test_search_users_endpoint_works()
    {
        $this->actingAs($this->adminUser, 'backpack');
        
        $response = $this->post('/admin/order/search-users', [
            'q' => 'user@test'
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => ['id', 'text']
        ]);
    }

    public function test_updates_user_wallet_after_successful_order()
    {
        $initialBalance = $this->targetUser->wallet_amount;
        
        $ordersService = app(\App\Services\Merchant\OrdersService::class);
        
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'top_up_provider_id' => $this->provider->id
        ];
        
        $ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);
        
        $this->targetUser->refresh();
        $this->assertEquals($initialBalance + 100.00, $this->targetUser->wallet_amount);
    }

    public function test_creates_transaction_record()
    {
        $ordersService = app(\App\Services\Merchant\OrdersService::class);
        
        $data = [
            'title' => 'Admin Top-up',
            'amount' => 100.00,
            'top_up_provider_id' => $this->provider->id
        ];
        
        $ordersService->createAdminTopUp($this->targetUser, $data, $this->adminUser);
        
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->targetUser->id,
            'type' => 'credit',
            'amount' => 100.00,
            'status' => 'active'
        ]);
    }
}