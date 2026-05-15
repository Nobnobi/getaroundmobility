<?php

require __DIR__ . '/../vendor/autoload.php';

echo "<pre>";

echo "CLASS EXISTS: ";
var_dump(class_exists("App\\Controllers\\HomeController"));

echo "\nALL LOADED CLASSES (partial check):\n";
print_r(array_filter(get_declared_classes(), function($c) {
    return str_contains($c, 'Home');
}));

echo "</pre>";