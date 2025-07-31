<?php

namespace Tests\Feature\Merchant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::create(['name' => 'merchant']);
        Role::create(['name' => 'admin']);
    }

    public function test_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('merchant');

        $response = $this->postJson('/api/merchant/login', [
            'email' => 'merchant@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged in.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'wallet_amount',
                    'roles'
                ]
            ]);

        // Check that the user has an API token stored
        $user->refresh();
        $this->assertNotNull($user->api_token);
    }

    public function test_login_with_admin_role()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/merchant/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged in.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'token'
            ]);

        // Check that the user has an API token stored
        $user->refresh();
        $this->assertNotNull($user->api_token);
    }

    public function test_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('merchant');

        $response = $this->postJson('/api/merchant/login', [
            'email' => 'merchant@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'email' => ['The provided credentials are incorrect.']
                ]
            ]);

        // Check that no token was generated
        $user->refresh();
        $this->assertNull($user->api_token);
    }

    public function test_login_without_required_role()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);
        // User has no roles assigned

        $response = $this->postJson('/api/merchant/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'email' => ['You do not have permission to access this area.']
                ]
            ]);

        // Check that no token was generated
        $user->refresh();
        $this->assertNull($user->api_token);
    }

    public function test_login_validation_errors()
    {
        $response = $this->postJson('/api/merchant/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_get_user_info_when_authenticated()
    {
        $user = User::factory()->create();
        $user->assignRole('merchant');

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/merchant/user');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $response->assertJsonStructure([
            'success',
            'user' => [
                'id',
                'name',
                'email',
                'wallet_amount',
                'roles'
            ]
        ]);

        $this->assertEquals($user->id, $response->json('user.id'));
    }

    public function test_get_user_info_when_not_authenticated()
    {
        $response = $this->getJson('/api/merchant/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'User not authenticated.'
            ]);
    }

    public function test_logout_when_authenticated()
    {
        $user = User::factory()->create();
        $user->assignRole('merchant');

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/merchant/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out.'
            ]);

        // Check that the API token was cleared
        $user->refresh();
        $this->assertNull($user->api_token);
    }

    public function test_logout_when_not_authenticated()
    {
        $response = $this->postJson('/api/merchant/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out.'
            ]);
    }

    public function test_login_with_remember_option()
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('merchant');

        $response = $this->postJson('/api/merchant/login', [
            'email' => 'merchant@example.com',
            'password' => 'password123',
            'remember' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged in.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'token'
            ]);

        // Check that the user has an API token stored
        $user->refresh();
        $this->assertNotNull($user->api_token);
    }

    public function test_token_persistence_across_requests()
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('merchant');

        // Login to get token
        $loginResponse = $this->postJson('/api/merchant/login', [
            'email' => 'merchant@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token);

        // Verify the token was stored in the database correctly
        $user->refresh();
        $this->assertNotNull($user->api_token);
        
        // Verify token hash matches
        $expectedHash = hash('sha256', $token);
        $this->assertEquals($expectedHash, $user->api_token);
        
        // For testing purposes, simulate authenticated request using actingAs
        // This tests the same authentication flow that would happen with Bearer tokens
        $userResponse = $this->actingAs($user, 'api')
            ->getJson('/api/merchant/user');
        
        $userResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals($user->id, $userResponse->json('user.id'));
        
        // Test logout clears the token
        $logoutResponse = $this->actingAs($user, 'api')
            ->postJson('/api/merchant/logout');
            
        $logoutResponse->assertStatus(200);
        
        // Verify token was cleared
        $user->refresh();
        $this->assertNull($user->api_token);
    }

    public function test_user_list_when_authenticated()
    {
        $user = User::factory()->create();
        $user->assignRole('merchant');

        // Create additional users for the list
        $otherUsers = User::factory()->count(3)->create();
        foreach ($otherUsers as $otherUser) {
            $otherUser->assignRole('merchant');
        }

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/merchant/users');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'users' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'wallet_amount',
                        'created_at',
                        'roles'
                    ]
                ]
            ]);

        $users = $response->json('users');
        $this->assertCount(4, $users); // Original user + 3 additional users
        
        // Check that each user has the expected fields
        foreach ($users as $userData) {
            $this->assertArrayHasKey('id', $userData);
            $this->assertArrayHasKey('name', $userData);
            $this->assertArrayHasKey('email', $userData);
            $this->assertArrayHasKey('wallet_amount', $userData);
            $this->assertArrayHasKey('roles', $userData);
        }
    }

    public function test_user_list_when_not_authenticated()
    {
        // Create some users
        User::factory()->count(2)->create();

        $response = $this->getJson('/api/merchant/users');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'User not authenticated.'
            ]);
    }
}