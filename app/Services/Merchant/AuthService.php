<?php

namespace App\Services\Merchant;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        Auth::login($user, $credentials['remember'] ?? false);

        return [
            'user' => $user->load('roles'),
            'message' => 'Successfully logged in.'
        ];
    }

    public function getUserInfo(): array
    {
        $user = Auth::user();
        
        if (!$user) {
            throw new \Exception('User not authenticated.');
        }

        return [
            'user' => $user->load('roles')
        ];
    }

    public function logout(): void
    {
        Auth::logout();
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