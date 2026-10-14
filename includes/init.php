<?php
declare(strict_types=1);

/**
 * Bootstrap: config, session, helpers.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => BASE_PATH === '' ? '/' : rtrim(BASE_PATH, '/') . '/',
    'secure'   => $secure,
    'httponly'  => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
