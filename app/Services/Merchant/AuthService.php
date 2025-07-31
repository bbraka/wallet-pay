<?php

namespace App\Services\Merchant;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->hasAnyRole(['merchant', 'admin'])) {
            throw ValidationException::withMessages([
                'email' => ['You do not have permission to access this area.'],
            ]);
        }

        // Generate simple API token
        $token = Str::random(60);
        $user->api_token = hash('sha256', $token);
        $user->save();

        return [
            'user' => $user->load('roles'),
            'token' => $token,
            'message' => 'Successfully logged in.'
        ];
    }

    public function getUserInfo(): array
    {
        $user = Auth::guard('api')->user();
        
        if (!$user) {
            throw new \Exception('User not authenticated.');
        }

        return [
            'user' => $user->load('roles')
        ];
    }

    public function logout(): void
    {
        $user = Auth::guard('api')->user();
        if ($user) {
            // Clear the API token
            $user->api_token = null;
            $user->save();
        }
    }

    public function getUserList(): array
    {
        $users = User::select('id', 'name', 'email', 'wallet_amount', 'created_at')
            ->with('roles:name')
            ->get();

        return [
            'users' => $users
        ];
    }
}