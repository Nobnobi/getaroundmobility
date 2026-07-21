<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Utils/Database.php';
$pdo = \App\Utils\Database::getInstance();
$stmt = $pdo->query('SHOW CREATE TABLE orders');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'];
