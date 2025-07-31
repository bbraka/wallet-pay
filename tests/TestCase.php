<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        // Force test database configuration before any database connections are made
        $app['config']->set('database.connections.mysql.database', 'user_wallet_app_test');
        
        return $app;
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing database connections to force new connections with updated config
        DB::purge('mysql');
        
        // Verify we're using the test database
        $dbName = DB::connection()->getDatabaseName();
        if ($dbName !== 'user_wallet_app_test') {
            throw new \Exception("Test is using wrong database: $dbName. Expected: user_wallet_app_test");
        }
    }

    /**
     * Generate a unique email for testing to avoid constraint violations.
     */
    protected function uniqueEmail(string $prefix = 'test'): string
    {
        return $prefix . '-' . uniqid() . '@test.com';
    }

}
