<?php

declare(strict_types=1);

define('APP_ROOT', __DIR__);
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('CONFIG_FILE', STORAGE_PATH . '/config.php');

spl_autoload_register(static function (string $class): void {
    $prefix = 'ManhwaPortal\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = APP_ROOT . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/Core/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = STORAGE_PATH . '/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0755, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_name('manhwa_portal_cms');
    session_start();
}
