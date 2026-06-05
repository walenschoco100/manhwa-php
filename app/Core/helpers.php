<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    if (!is_file(CONFIG_FILE)) {
        return $config = [];
    }

    $loaded = require CONFIG_FILE;
    return $config = is_array($loaded) ? $loaded : [];
}

function is_installed(): bool
{
    return is_file(CONFIG_FILE);
}

function app_url(string $path = ''): string
{
    $base = rtrim((string) (app_config()['app_url'] ?? ''), '/');
    $path = '/' . ltrim($path, '/');

    if ($base !== '') {
        return $base . ($path === '/' ? '' : $path);
    }

    return $path;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'item-' . bin2hex(random_bytes(4));
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path), true, 302);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('CSRF token tidak valid.');
    }
}

function render(string $view, array $data = [], string $layout = 'public'): void
{
    extract($data, EXTR_SKIP);
    $viewFile = APP_ROOT . '/Views/' . $view . '.php';
    $layoutFile = APP_ROOT . '/Views/' . $layout . '-layout.php';

    if (!is_file($viewFile) || !is_file($layoutFile)) {
        http_response_code(500);
        exit('View tidak ditemukan.');
    }

    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require $layoutFile;
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    return '/' . trim((string) $path, '/');
}

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!admin_is_logged_in()) {
        redirect('/admin/login');
    }
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function human_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $time = strtotime($date);
    return $time ? date('d M Y', $time) : $date;
}

function setting(string $key, ?string $default = null): ?string
{
    return \ManhwaPortal\Core\Database::setting($key, $default);
}

