<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TopUpProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Bank Transfer',
                'code' => 'BANK',
                'description' => 'Bank transfer or wire transfer',
                'is_active' => true,
                'requires_reference' => true,
            ],
            [
                'name' => 'Cash Deposit',
                'code' => 'CASH',
                'description' => 'Physical cash deposit',
                'is_active' => true,
                'requires_reference' => false,
            ],
            [
                'name' => 'Money Order',
                'code' => 'MONEY_ORDER',
                'description' => 'Money order payment',
                'is_active' => true,
                'requires_reference' => true,
            ],
            [
                'name' => 'Credit Card',
                'code' => 'CREDIT_CARD',
                'description' => 'Credit card payment',
                'is_active' => true,
                'requires_reference' => true,
            ],
            [
                'name' => 'PayPal',
                'code' => 'PAYPAL',
                'description' => 'PayPal payment',
                'is_active' => true,
                'requires_reference' => true,
            ],
            [
                'name' => 'Admin Adjustment',
                'code' => 'ADMIN_ADJUSTMENT',
                'description' => 'Manual adjustment by administrator',
                'is_active' => true,
                'requires_reference' => false,
            ],
        ];

        foreach ($providers as $provider) {
            DB::table('top_up_providers')->updateOrInsert(
                ['code' => $provider['code']],
                array_merge($provider, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Default top-up providers created successfully.');
    }
}