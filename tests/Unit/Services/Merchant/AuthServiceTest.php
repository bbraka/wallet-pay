<?php

namespace Tests\Unit\Services\Merchant;

use App\Models\User;
use App\Services\Merchant\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthService();
        
        Role::firstOrCreate(['name' => 'merchant']);
        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_login_with_valid_credentials_and_merchant_role()
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('merchant');

        $credentials = [
            'email' => 'merchant@example.com',
            'password' => 'password123',
        ];

        $result = $this->authService->login($credentials);

        // Check that token was generated and stored
        $user->refresh();
        $this->assertNotNull($user->api_token);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertEquals('Successfully logged in.', $result['message']);
        $this->assertArrayHasKey('user', $result);
        $this->assertTrue($result['user']->hasRole('merchant'));
        $this->assertEquals($user->id, $result['user']->id);
    }

    public function test_login_with_valid_credentials_and_admin_role()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('admin');

        $credentials = [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ];

        $result = $this->authService->login($credentials);

        // Check that token was generated and stored
        $user->refresh();
        $this->assertNotNull($user->api_token);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertEquals('Successfully logged in.', $result['message']);
        $this->assertArrayHasKey('user', $result);
        $this->assertTrue($result['user']->hasRole('admin'));
        $this->assertEquals($user->id, $result['user']->id);
    }

    public function test_login_with_invalid_email()
    {
        $credentials = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        $this->expectException(ValidationException::class);
        $this->authService->login($credentials);
    }

    public function test_login_with_invalid_password()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct_password'),
        ]);
        $user->assignRole('merchant');

        $credentials = [
            'email' => 'user@example.com',
            'password' => 'wrong_password',
        ];

        $this->expectException(ValidationException::class);
        $this->authService->login($credentials);
    }

    public function test_login_without_required_role()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);
        // User has no roles assigned

        $credentials = [
            'email' => 'user@example.com',
            'password' => 'password123',
        ];

        $this->expectException(ValidationException::class);
        $this->authService->login($credentials);
    }

    public function test_get_user_info_when_authenticated()
    {
        $user = User::factory()->create();
        $user->assignRole('merchant');
        
        // Simulate token authentication by using the api guard
        Auth::guard('api')->setUser($user);

        $result = $this->authService->getUserInfo();

        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($user->id, $result['user']->id);
        $this->assertTrue($result['user']->hasRole('merchant'));
    }

    public function test_get_user_info_when_not_authenticated()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not authenticated.');
        
        $this->authService->getUserInfo();
    }

    public function test_logout()
    {
        $user = User::factory()->create();
        // Generate an API token first
        $user->api_token = hash('sha256', 'test-token');
        $user->save();
        
        Auth::guard('api')->setUser($user);
        $this->assertNotNull(Auth::guard('api')->user());

        $this->authService->logout();

        // Check that the token was cleared
        $user->refresh();
        $this->assertNull($user->api_token);
    }

    public function test_login_with_remember_option()
    {
        $user = User::factory()->create([
            'email' => 'merchant@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('merchant');

        $credentials = [
            'email' => 'merchant@example.com',
            'password' => 'password123',
            'remember' => true,
        ];

        $result = $this->authService->login($credentials);

        // Check that token was generated and stored
        $user->refresh();
        $this->assertNotNull($user->api_token);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertEquals('Successfully logged in.', $result['message']);
        $this->assertEquals($user->id, $result['user']->id);
    }
}