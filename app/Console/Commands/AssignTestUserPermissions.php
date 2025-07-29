<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Permission;

class AssignTestUserPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:assign-test-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign all permissions to test user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $testUser = User::where('email', 'test@example.com')->first();
        
        if (!$testUser) {
            $this->error('Test user not found');
            return 1;
        }

        $permissions = [
            'access admin area',
            'manage users', 
            'manage roles', 
            'manage permissions', 
            'manage orders', 
            'manage transactions', 
            'view reports'
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission) {
                $testUser->givePermissionTo($permission);
                $this->info("Granted: {$permissionName}");
            } else {
                $this->warn("Permission not found: {$permissionName}");
            }
        }

        $this->info('Test user now has permissions: ' . $testUser->getAllPermissions()->pluck('name')->implode(', '));
        return 0;
    }
}
