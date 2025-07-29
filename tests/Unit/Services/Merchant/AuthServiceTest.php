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
        
        Role::create(['name' => 'merchant']);
        Role::create(['name' => 'admin']);
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

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
        $this->assertEquals('Successfully logged in.', $result['message']);
        $this->assertArrayHasKey('user', $result);
        $this->assertTrue($result['user']->hasRole('merchant'));
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

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
        $this->assertEquals('Successfully logged in.', $result['message']);
        $this->assertArrayHasKey('user', $result);
        $this->assertTrue($result['user']->hasRole('admin'));
    }

    public function test_login_with_invalid_email()
    {
        $credentials = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        $this->expectException(ValidationException::class);
        $this->authService->login($credentials);
        $this->assertFalse(Auth::check());
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
        $this->assertFalse(Auth::check());
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
        $this->assertFalse(Auth::check());
    }

    public function test_get_user_info_when_authenticated()
    {
        $user = User::factory()->create();
        $user->assignRole('merchant');
        Auth::login($user);

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
        Auth::login($user);
        $this->assertTrue(Auth::check());

        $this->authService->logout();

        $this->assertFalse(Auth::check());
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

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
        $this->assertEquals('Successfully logged in.', $result['message']);
    }
}