<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'access admin area',
            'manage users',
            'manage roles',
            'manage permissions',
            'manage orders',
            'manage transactions',
            'view reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create admin role and assign permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'access admin area',
            'manage users',
            'manage roles', 
            'manage permissions',
            'manage orders',
            'manage transactions',
            'view reports',
        ]);

        // Create merchant role
        $merchantRole = Role::firstOrCreate(['name' => 'merchant']);
        // Merchants don't get any special permissions by default
    }
}
