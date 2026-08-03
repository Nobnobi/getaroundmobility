<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
$port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
$db = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'getaround_db');
$user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'getaroundmobility');
$pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? 'itup420');

$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$checks = [];
$checks['orders_has_power_chair_handedness_col'] = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='orders' AND column_name='power_chair_handedness'")->fetchColumn();
$checks['order_items_has_power_chair_handedness_col'] = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='order_items' AND column_name='power_chair_handedness'")->fetchColumn();
$checks['users_has_email_2_index'] = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='users' AND index_name='email_2'")->fetchColumn();
$checks['powerchair_items_missing_handedness'] = (int)$pdo->query("SELECT COUNT(*) FROM order_items oi JOIN products p ON p.product_id = oi.product_id WHERE LOWER(CONCAT(COALESCE(p.product_name, ''), ' ', COALESCE(oi.product_name, ''))) LIKE '%power%chair%' AND (oi.power_chair_handedness IS NULL OR TRIM(oi.power_chair_handedness) = '')")->fetchColumn();
$checks['checkout_sessions_exists'] = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='checkout_sessions'")->fetchColumn();
$checks['payment_events_exists'] = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='payment_events'")->fetchColumn();
$checks['order_documents_metadata_exists'] = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='order_documents_metadata'")->fetchColumn();

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
