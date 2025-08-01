#!/bin/bash
set -e

echo "Starting Laravel application setup..."

# Wait for database to be ready
echo "Waiting for database connection..."
php -r "
set_time_limit(120);
\$host = \$_ENV['DB_HOST'] ?? 'mysql';
\$port = \$_ENV['DB_PORT'] ?? '3306';
\$attempts = 0;
\$maxAttempts = 60;

echo 'Connecting to database at ' . \$host . ':' . \$port . PHP_EOL;

do {
    \$connection = @fsockopen(\$host, \$port, \$errno, \$errstr, 1);
    if (\$connection) {
        echo 'Database is ready!' . PHP_EOL;
        fclose(\$connection);
        break;
    }
    echo 'Waiting for database... attempt ' . (\$attempts + 1) . '/' . \$maxAttempts . PHP_EOL;
    \$attempts++;
    if (\$attempts >= \$maxAttempts) {
        echo 'Database connection timeout after ' . \$maxAttempts . ' attempts!' . PHP_EOL;
        exit(1);
    }
    sleep(2);
} while (true);
"

# Fix permissions before modifying files
echo "Fixing file permissions..."
sudo chown -R devuser:www-data /var/www/html/resources /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
sudo chmod -R 775 /var/www/html/resources /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Install Composer dependencies
echo "Installing Composer dependencies..."
if [ ! -f "vendor/autoload.php" ]; then
    echo "Setting up Composer with GitHub token..."
    if [ ! -z "$GITHUB_MCP_SERVER_ACCESS_TOKEN" ]; then
        composer config --global github-oauth.github.com "$GITHUB_MCP_SERVER_ACCESS_TOKEN"
    fi
    
    echo "Installing dependencies with optimized settings..."
    # Check if we're in development environment
    if [ "${APP_ENV:-local}" = "production" ]; then
        echo "Production environment detected - installing without dev dependencies..."
        COMPOSER_MEMORY_LIMIT=-1 COMPOSER_PROCESS_TIMEOUT=600 composer install \
            --no-interaction \
            --optimize-autoloader \
            --no-dev \
            --prefer-dist \
            --no-progress \
            --no-suggest
    else
        echo "Development environment detected - installing with dev dependencies (includes PHPUnit)..."
        COMPOSER_MEMORY_LIMIT=-1 COMPOSER_PROCESS_TIMEOUT=600 composer install \
            --no-interaction \
            --optimize-autoloader \
            --prefer-dist \
            --no-progress \
            --no-suggest
    fi
else
    echo "Composer dependencies already installed"
    # Ensure dev dependencies are installed in development mode
    if [ "${APP_ENV:-local}" != "production" ] && [ ! -f "vendor/bin/phpunit" ]; then
        echo "PHPUnit not found - installing dev dependencies..."
        COMPOSER_MEMORY_LIMIT=-1 COMPOSER_PROCESS_TIMEOUT=600 composer install \
            --no-interaction \
            --optimize-autoloader \
            --prefer-dist \
            --no-progress \
            --no-suggest
    fi
fi

# Install NPM dependencies
echo "Installing NPM dependencies..."
if [ ! -d "node_modules" ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
    echo "Installing NPM packages..."
    npm install --production --no-optional
else
    echo "NPM dependencies already installed"
fi

# Generate application key if not set
echo "Checking application key..."
if [ ! -f ".env" ] || ! grep -q "^APP_KEY=" .env || grep -q "^APP_KEY=$" .env; then
    echo "Generating application key..."
    php artisan key:generate --ansi --force
else
    echo "Application key already set"
fi

# Create storage link if it doesn't exist
echo "Setting up storage link..."
if [ ! -L "public/storage" ]; then
    echo "Creating storage link..."
    php artisan storage:link
else
    echo "Storage link already exists"
fi

# Test Laravel database connection
echo "Testing Laravel database connection..."
php artisan tinker --execute="
try {
    DB::connection()->getPdo();
    echo 'Laravel database connection successful!' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
" || {
    echo "Failed to connect to database with Laravel"
    exit 1
}

# Run migrations
echo "Running database migrations..."
php artisan migrate --force || {
    echo "Migration failed, but continuing..."
}

# Setup test database if not in production
if [ "${APP_ENV:-local}" != "production" ]; then
    echo "Setting up test database..."
    
    # Install Chrome driver for Laravel Dusk
    echo "Installing Chrome driver for Laravel Dusk..."
    php artisan dusk:chrome-driver --detect || echo "Chrome driver installation failed, but continuing..."
    
    # Create test database if it doesn't exist
    php artisan tinker --execute="
        try {
            // Configure root connection
            config(['database.connections.mysql_root' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST'),
                'port' => env('DB_PORT'),
                'database' => '',
                'username' => 'root',
                'password' => env('MYSQL_ROOT_PASSWORD'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);
            
            // Ensure clean test database setup
            DB::connection('mysql_root')->statement('DROP DATABASE IF EXISTS user_wallet_app_test');
            DB::connection('mysql_root')->statement('CREATE DATABASE user_wallet_app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            DB::connection('mysql_root')->statement('GRANT ALL PRIVILEGES ON user_wallet_app_test.* TO \"user_wallet_user\"@\"%\"');
            DB::connection('mysql_root')->statement('FLUSH PRIVILEGES');
            echo 'Test database setup completed successfully!' . PHP_EOL;
        } catch (Exception \$e) {
            echo 'Test database setup failed: ' . \$e->getMessage() . PHP_EOL;
        }
    " || echo "Test database creation failed, but continuing..."
    
    # Run migrations on test database
    php artisan migrate --database=mysql_testing --force || {
        echo "Test database migration failed, but continuing..."
    }
fi

# Seed the database
echo "Seeding database..."
php artisan db:seed --force || {
    echo "Database seeding failed, but continuing..."
}

# Seed wallet providers
echo "Seeding wallet providers..."
php artisan db:seed --class=TopUpProviderSeeder --force || {
    echo "Top-up provider seeding failed, but continuing..."
}

echo "User setup completed via seeder"

# Clear and cache config
echo "Optimizing application..."
php artisan config:clear
php artisan config:cache || echo "Config cache failed, continuing..."
php artisan route:clear  
php artisan route:cache || echo "Route cache failed, continuing..."
php artisan view:clear
php artisan view:cache || echo "View cache failed, continuing..."

# Fix broken CDN URLs in Backpack views (with proper permissions)
echo "Fixing Backpack CDN URLs..."
if [ -f "/var/www/html/resources/views/vendor/backpack/crud/inc/datatables_logic.blade.php" ]; then
    sudo sed -i 's|responsive/2.4.0|responsive/2.5.0|g' /var/www/html/resources/views/vendor/backpack/crud/inc/datatables_logic.blade.php 2>/dev/null || true
    sudo sed -i 's|fixedheader/3.3.1|fixedheader/3.4.0|g' /var/www/html/resources/views/vendor/backpack/crud/inc/datatables_logic.blade.php 2>/dev/null || true
fi

if [ -f "/var/www/html/resources/views/vendor/backpack/crud/list.blade.php" ]; then
    sudo sed -i 's|responsive/2.4.0|responsive/2.5.0|g' /var/www/html/resources/views/vendor/backpack/crud/list.blade.php 2>/dev/null || true
    sudo sed -i 's|fixedheader/3.3.1|fixedheader/3.4.0|g' /var/www/html/resources/views/vendor/backpack/crud/list.blade.php 2>/dev/null || true
fi

# Clear and recache Basset assets
echo "Caching Basset assets..."
php artisan basset:clear || echo "Basset clear failed, continuing..."
php artisan basset:cache || echo "Basset cache failed, continuing..."

echo "Laravel application setup completed successfully!"

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec "$@"