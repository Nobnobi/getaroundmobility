<?php

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isAdminSurface = str_starts_with($requestPath, '/admin') || str_starts_with($requestPath, '/walkin-booking');
if ($isAdminSurface) {
    $cspReportOnly = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $cspReportOnly));
} else {
    $publicCspReportOnly = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://js.stripe.com https://www.paypal.com https://www.paypalobjects.com https://connect.facebook.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com",
        "img-src 'self' data: blob: https:",
        "connect-src 'self' https://api.stripe.com https://www.paypal.com https://*.paypal.com https://connect.facebook.net",
        "frame-src 'self' https://js.stripe.com https://hooks.stripe.com https://www.paypal.com https://www.sandbox.paypal.com https://www.facebook.com",
    ];
    header('Content-Security-Policy: ' . implode('; ', $publicCspReportOnly));
}

// =============================
// ERROR HANDLING (PRODUCTION SAFE)
// =============================
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');
error_reporting(E_ALL);

// =============================
// BASE PATH (LOCKED & RELIABLE)
// =============================
$basePath = realpath(__DIR__ . '/..');

if ($basePath === false) {
    http_response_code(500);
    error_log("Bootstrap error: base path resolution failed");
    exit('System configuration error');
}

// =============================
// PATH HELPERS
// =============================
$autoloadPath = $basePath . '/vendor/autoload.php';
$envPath      = $basePath . '/.env';
$routesPath   = $basePath . '/src/Routes/index.php';

// =============================
// VALIDATE VENDOR
// =============================
if (!is_file($autoloadPath)) {
    http_response_code(500);
    error_log("Bootstrap error: vendor/autoload.php missing at $autoloadPath");
    exit('System dependencies missing');
}

require $autoloadPath;

// =============================
// DOTENV (FAIL SAFE)
// =============================
if (class_exists('Dotenv\\Dotenv') && is_file($envPath)) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();
    } catch (Throwable $e) {
        // Never crash production due to env issues
        error_log("Dotenv error: " . $e->getMessage());
    }
}

// =============================
// VALIDATE ROUTES
// =============================
if (!is_file($routesPath)) {
    http_response_code(500);
    error_log("Bootstrap error: routes missing at $routesPath");
    exit('Application routes missing');
}

// =============================
// BOOT APPLICATION
// =============================
try {
    require $routesPath;
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Application crash: " . $e->getMessage());
    exit('Application error');
}