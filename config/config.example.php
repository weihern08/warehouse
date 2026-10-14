<?php
declare(strict_types=1);

/**
 * Application configuration example.
 * Copy to config.php and fill in your values.
 */

define('APP_NAME', 'Warehouse Management System');
define('APP_ENV', 'production'); // development | production

define('BASE_PATH', '/warehouse-cpanel');
define('APP_URL', 'https://your-domain.com/warehouse-cpanel');

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

define('DB_NAME_CANDIDATES', [
    'your_database_name',
]);

define('SESSION_NAME', 'warehouse_session');
define('CSRF_TOKEN_KEY', '_csrf_token');

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 300);
define('PASSWORD_MIN_LENGTH', 8);
