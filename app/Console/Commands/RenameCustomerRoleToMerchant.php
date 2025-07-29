<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class RenameCustomerRoleToMerchant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'role:rename-customer-to-merchant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rename customer role to merchant role in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Find the customer role
        $customerRole = Role::where('name', 'customer')->first();
        
        if (!$customerRole) {
            $this->info('Customer role not found in database.');
            return 0;
        }

        // Update the role name to merchant
        $customerRole->name = 'merchant';
        $customerRole->save();

        $this->info('Successfully renamed customer role to merchant.');
        
        // Show affected users
        $users = $customerRole->users;
        if ($users->count() > 0) {
            $this->info('Users with merchant role:');
            foreach ($users as $user) {
                $this->line('- ' . $user->email);
            }
        }

        return 0;
    }
}