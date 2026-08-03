<?php

declare(strict_types=1);

use App\Models\AdminLoginAttemptModel;

$basePath = dirname(__DIR__);
$autoload = $basePath . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php\n");
    exit(1);
}

require $autoload;

if (class_exists('Dotenv\\Dotenv') && is_file($basePath . '/.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();
    } catch (Throwable $e) {
        fwrite(STDERR, "Dotenv warning: " . $e->getMessage() . "\n");
    }
}

$days = isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : 90;
$days = max(1, min(3650, $days));

$attemptsModel = new AdminLoginAttemptModel();
$deletedAttempts = $attemptsModel->pruneOlderThanDays($days);

$logFiles = [
    $basePath . '/public/mail-debug-log.txt',
    $basePath . '/public/order-debug-log.txt',
    $basePath . '/public/paypal-order-logs.txt',
    $basePath . '/public/paypal-create-order-logs.txt',
    $basePath . '/public/stripe-webhook-logs.txt',
    $basePath . '/mail-debug-log.txt',
    $basePath . '/order-debug-log.txt',
    $basePath . '/stripe-webhook-logs.txt',
    $basePath . '/stripe-debug.txt',
];

$deletedLogs = 0;
$deletedLogBytes = 0;
$cutoffTs = time() - ($days * 86400);

foreach ($logFiles as $logPath) {
    if (!is_file($logPath)) {
        continue;
    }

    $mtime = @filemtime($logPath);
    if ($mtime !== false && $mtime >= $cutoffTs) {
        continue;
    }

    $size = @filesize($logPath);
    if (@unlink($logPath)) {
        $deletedLogs++;
        if ($size !== false) {
            $deletedLogBytes += (int)$size;
        }
    }
}

echo json_encode([
    'success' => true,
    'retention_days' => $days,
    'deleted_login_attempt_rows' => $deletedAttempts,
    'deleted_log_files' => $deletedLogs,
    'deleted_log_bytes' => $deletedLogBytes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
