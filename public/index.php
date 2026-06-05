<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use ManhwaPortal\Controllers\AdminController;
use ManhwaPortal\Controllers\ApiController;
use ManhwaPortal\Controllers\InstallController;
use ManhwaPortal\Controllers\PublicController;

$path = current_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!is_installed() && !str_starts_with($path, '/install')) {
    redirect('/install');
}

if (str_starts_with($path, '/api/') || $path === '/data/manhwa.json') {
    ini_set('display_errors', '0');
    (new ApiController())->handle($path, $method);
    exit;
}

if ($path === '/install') {
    (new InstallController())->show();
    exit;
}

if ($path === '/install/run' && $method === 'POST') {
    (new InstallController())->run();
    exit;
}

if ($path === '/admin/login') {
    if (admin_is_logged_in()) {
        redirect('/admin');
    }
    serve_static(PUBLIC_PATH . '/admin-login-static.html', 'text/html; charset=utf-8');
    exit;
}

if (str_starts_with($path, '/admin')) {
    if (!admin_is_logged_in()) {
        redirect('/admin/login?next=' . rawurlencode($path));
    }
    serve_static(PUBLIC_PATH . '/admin-static.html', 'text/html; charset=utf-8');
    exit;
}

if ($path === '/sitemap.xml' || $path === '/robots.txt') {
    $public = new PublicController();
    $path === '/sitemap.xml' ? $public->sitemap() : $public->robots();
    exit;
}

serve_static(PUBLIC_PATH . '/app-index.html', 'text/html; charset=utf-8');

function serve_static(string $file, string $contentType): void
{
    if (!is_file($file)) {
        http_response_code(404);
        echo 'Not found';
        return;
    }
    header('Content-Type: ' . $contentType);
    readfile($file);
}
