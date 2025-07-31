<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Order;
use App\Models\Transaction;
use App\Enums\OrderType;
use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Enums\TransactionStatus;
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

        // Create supporting transactions for initial wallet amounts
        $this->createSupportingTransactions($adminUser, 1000.00, 'Initial admin wallet balance');
        $this->createSupportingTransactions($testUser, 500.00, 'Initial user wallet balance');
    }

    /**
     * Create supporting order and transaction for initial wallet balance
     */
    private function createSupportingTransactions(User $user, float $amount, string $description): void
    {
        // Check if supporting transactions already exist
        $existingTransactions = Transaction::where('user_id', $user->id)
            ->where('description', 'LIKE', 'Initial % wallet balance')
            ->count();

        if ($existingTransactions > 0) {
            $this->command->info("Supporting transactions already exist for user: {$user->email}");
            return;
        }

        // Create initial top-up order
        $order = Order::create([
            'title' => 'Initial Wallet Balance',
            'amount' => $amount,
            'status' => OrderStatus::COMPLETED,
            'order_type' => OrderType::ADMIN_TOP_UP,
            'description' => $description,
            'user_id' => $user->id,
            'payment_completion_date' => now(),
        ]);

        // Create credit transaction to support the wallet balance
        Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::CREDIT,
            'amount' => $amount,
            'status' => TransactionStatus::ACTIVE,
            'description' => $description,
            'created_by' => $user->id, // Self-created for initial balance
            'order_id' => $order->id,
        ]);

        $this->command->info("Created supporting order and transaction for {$user->email}: ${amount}");
    }
}
