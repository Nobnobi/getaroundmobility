<?php

echo "<pre>";

echo "CURRENT FILE (__FILE__): " . __FILE__ . PHP_EOL;
echo "CURRENT DIR (__DIR__): " . __DIR__ . PHP_EOL;

echo PHP_EOL;

echo "dirname(__DIR__): " . dirname(__DIR__) . PHP_EOL;
echo "dirname(dirname(__DIR__)): " . dirname(dirname(__DIR__)) . PHP_EOL;
echo "realpath(__DIR__): " . realpath(__DIR__) . PHP_EOL;
echo "realpath(dirname(__DIR__)): " . realpath(dirname(__DIR__)) . PHP_EOL;

echo PHP_EOL;

$base = dirname(__DIR__);
echo "CHECK VENDOR PATH: " . $base . "/vendor/autoload.php" . PHP_EOL;
echo "VENDOR EXISTS: " . (file_exists($base . "/vendor/autoload.php") ? "YES" : "NO") . PHP_EOL;

echo PHP_EOL;

echo "CHECK SRC ROUTES: " . $base . "/src/Routes/index.php" . PHP_EOL;
echo "ROUTES EXISTS: " . (file_exists($base . "/src/Routes/index.php") ? "YES" : "NO") . PHP_EOL;

echo "</pre>";