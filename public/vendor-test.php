<?php

echo "<pre>";

$basePath = realpath(__DIR__ . '/..');

echo "BASE PATH: $basePath\n\n";

// 1. Check autoload
$autoload = $basePath . '/vendor/autoload.php';
echo "Autoload exists: " . (file_exists($autoload) ? "YES" : "NO") . "\n";

// 2. Try loading autoload
try {
    require $autoload;
    echo "Autoload loaded: YES\n";
} catch (Throwable $e) {
    echo "Autoload ERROR: " . $e->getMessage() . "\n";
}

// 3. Check Composer classmap
$checkFiles = [
    '/vendor/composer/autoload_real.php',
    '/vendor/composer/ClassLoader.php',
    '/vendor/composer/autoload_static.php',
];

echo "\nComposer core files:\n";
foreach ($checkFiles as $file) {
    echo $file . ": " . (file_exists($basePath . $file) ? "OK" : "MISSING") . "\n";
}

// 4. Check Dotenv specifically
echo "\nDotenv check:\n";
echo class_exists("Dotenv\\Dotenv") ? "Dotenv LOADED" : "Dotenv NOT LOADED";

echo "\n</pre>";