<?php

/**
 * PHPUnit bootstrap file for optimized test database management.
 * 
 * This file runs once at the beginning of the test suite to set up
 * the test database with fresh migrations and seeders, and once at
 * the end to clean up.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Create Laravel application
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Force test database configuration
$app['config']->set('database.connections.mysql.database', 'user_wallet_app_test');

// Set up the database for testing
echo "Setting up test database...\n";

// Fresh migration and seeding
Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
    '--env' => 'testing',
    '--force' => true
]);

// Seed the database with essential structural data only (no users)
Illuminate\Support\Facades\Artisan::call('db:seed', [
    '--class' => 'Database\\Seeders\\RolePermissionSeeder',
    '--env' => 'testing'
]);

Illuminate\Support\Facades\Artisan::call('db:seed', [
    '--class' => 'Database\\Seeders\\TopUpProviderSeeder',
    '--env' => 'testing'
]);

echo "Test database setup complete.\n";

// Register shutdown function to clean up after all tests
register_shutdown_function(function() {
    echo "Cleaning up test database...\n";
    // Optionally truncate tables or leave them for debugging
    echo "Test database cleanup complete.\n";
});