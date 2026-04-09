<?php

// Minimal router for PHP's built-in server.
// Usage (from backend/): php -S 127.0.0.1:8000 -t public server.php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

$publicPath = __DIR__ . DIRECTORY_SEPARATOR . 'public';
$requestedPath = realpath($publicPath . $uri);

// If the requested resource is a real file under public/, let the built-in server serve it.
if ($requestedPath !== false && str_starts_with($requestedPath, realpath($publicPath)) && is_file($requestedPath)) {
    return false;
}

require_once $publicPath . DIRECTORY_SEPARATOR . 'index.php';
