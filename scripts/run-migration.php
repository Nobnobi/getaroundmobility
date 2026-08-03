<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/run-migration.php <relative-sql-path>\n");
    exit(1);
}

$sqlPath = $basePath . DIRECTORY_SEPARATOR . ltrim($argv[1], '/\\');
if (!is_file($sqlPath)) {
    fwrite(STDERR, "SQL file not found: {$sqlPath}\n");
    exit(1);
}

$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
$port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
$db = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'getaround_db');
$user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'getaroundmobility');
$pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? 'itup420');

$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = file_get_contents($sqlPath);
if ($sql === false) {
    fwrite(STDERR, "Failed to read SQL file: {$sqlPath}\n");
    exit(1);
}

$sql = preg_replace('/^\s*--.*$/m', '', $sql);

$statements = preg_split('/;\s*\R/', $sql) ?: [];
$executed = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '') {
        continue;
    }

    $pdo->exec($statement);
    $executed++;
}

echo "Applied migration: {$argv[1]}\n";
echo "Executed statements: {$executed}\n";
