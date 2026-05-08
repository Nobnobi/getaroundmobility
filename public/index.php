<?php

require __DIR__ . '/../vendor/autoload.php';


// Load environment variables from project root
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$router = require __DIR__ . '/../src/Routes/index.php';