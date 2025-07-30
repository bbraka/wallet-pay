-- Create test database
CREATE DATABASE IF NOT EXISTS `user_wallet_app_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant permissions are handled by docker-entrypoint.sh automatically for the MYSQL_USER
-- but we need to ensure test database permissions
GRANT ALL PRIVILEGES ON `user_wallet_app_test`.* TO 'user_wallet_user'@'%';

-- Apply the changes
FLUSH PRIVILEGES;