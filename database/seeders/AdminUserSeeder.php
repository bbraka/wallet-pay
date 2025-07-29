<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'wallet_amount' => 1000.00,
            ]
        );

        // Create test user (merchant)
        $testUser = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'wallet_amount' => 500.00,
            ]
        );

        // Get roles
        $adminRole = Role::where('name', 'admin')->first();
        $merchantRole = Role::where('name', 'merchant')->first();

        if ($adminRole) {
            // Assign admin role to admin user
            $adminUser->syncRoles([$adminRole]);
            $this->command->info("Admin user created/updated: admin@example.com (password: password)");
        }

        if ($merchantRole) {
            // Assign merchant role to test user
            $testUser->syncRoles([$merchantRole]);
            $this->command->info("Test user created/updated: test@example.com (password: password) - merchant role");
        } else {
            // If no merchant role, remove all roles from test user
            $testUser->syncRoles([]);
            $this->command->info("Test user created/updated: test@example.com (password: password) - no admin access");
        }
    }
}
