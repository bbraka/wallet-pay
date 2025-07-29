<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class FixUserPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:fix-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix user permissions - admin gets all permissions, customer gets none';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get users
        $adminUser = User::find(4); // Admin User
        $customerUser = User::find(6); // Test User (customer)

        if (!$adminUser) {
            $this->error('Admin user (ID 4) not found');
            return 1;
        }

        if (!$customerUser) {
            $this->error('Customer user (ID 6) not found');
            return 1;
        }

        // Get admin role
        $adminRole = Role::where('name', 'admin')->first();
        if (!$adminRole) {
            $this->error('Admin role not found');
            return 1;
        }

        // Remove all permissions and roles from customer user
        $customerUser->syncPermissions([]);
        $customerUser->syncRoles([]);
        $this->info('Removed all permissions and roles from customer user: ' . $customerUser->email);

        // Ensure admin user has admin role
        if (!$adminUser->hasRole('admin')) {
            $adminUser->assignRole($adminRole);
            $this->info('Assigned admin role to: ' . $adminUser->email);
        }

        // Verify admin permissions
        $adminPermissions = $adminUser->getAllPermissions()->pluck('name')->toArray();
        $this->info('Admin user permissions: ' . implode(', ', $adminPermissions));
        
        $customerPermissions = $customerUser->getAllPermissions()->pluck('name')->toArray();
        $this->info('Customer user permissions: ' . (empty($customerPermissions) ? 'None' : implode(', ', $customerPermissions)));

        return 0;
    }
}
