<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RouteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_orders_route_exists(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->get('/api/merchant/orders');
            
        // Should not be 404
        $this->assertNotEquals(404, $response->getStatusCode());
    }
    
    public function test_basic_routes_work(): void
    {
        $response = $this->get('/api/schema');
        $this->assertNotEquals(404, $response->getStatusCode());
    }
}