# Database Setup

This document explains the database configuration for both development and testing environments.

## Overview

- **Main Database**: `user_wallet_app` - Used for development and production
- **Test Database**: `user_wallet_app_test` - Used exclusively for PHPUnit tests

## Configuration Files

### 1. Docker Configuration
- `docker-compose.yml`: Defines MySQL service
- `docker/mysql/init/01-create-test-database.sql`: Creates test database on container startup

### 2. Laravel Configuration
- `.env`: Main database configuration
- `config/database.php`: Defines both `mysql` and `mysql_testing` connections
- `phpunit.xml`: Configures tests to use `mysql_testing` connection

### 3. Key Settings

**phpunit.xml:**
```xml
<env name="DB_CONNECTION" value="mysql_testing"/>
<env name="DB_DATABASE" value="user_wallet_app_test"/>
```

**config/database.php:**
```php
'mysql_testing' => [
    'driver' => 'mysql',
    'database' => env('DB_TEST_DATABASE', 'user_wallet_app_test'),
    // ... other settings same as mysql connection
],
```

## Initial Setup

After container rebuild or fresh setup:

1. **Seed main database:**
   ```bash
   php artisan db:seed
   ```

2. **Verify separation:**
   ```bash
   php artisan test --filter="ExampleTest"
   # Main database should remain unaffected
   ```

## Database Isolation

- Tests use `RefreshDatabase` trait with the test database
- Main database data persists across test runs
- Each test gets a fresh database state without affecting production data

## Seeded Data

The main database includes:
- Admin user: `admin@example.com` (password: `password`)
- Test user: `test@example.com` (password: `password`)
- Sample transactions and orders
- Default top-up providers

## Troubleshooting

If tests are affecting the main database:
1. Clear config cache: `php artisan config:clear`
2. Verify phpunit.xml has `DB_CONNECTION=mysql_testing`
3. Check that `docker/mysql/init/01-create-test-database.sql` exists