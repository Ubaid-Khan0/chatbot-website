<?php

declare(strict_types=1);

use Nexum\Config;
use Nexum\Http;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Dependencies are not installed. Run: composer install --no-dev --optimize-autoloader\n");
}
require $autoload;

Config::load($root);
date_default_timezone_set('UTC');

$secure = Config::isProduction() || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name(Config::get('SESSION_NAME', 'nexum_admin_session'));
session_save_path($root . '/storage/sessions');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
Http::securityHeaders();

return $root;
