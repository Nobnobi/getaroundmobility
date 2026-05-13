<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$pathCandidates = [
	dirname(__DIR__),
	__DIR__,
	dirname(dirname(__DIR__)),
];

$autoloadPath = null;
$basePath = null;
foreach ($pathCandidates as $candidate) {
	$candidateAutoload = $candidate . '/vendor/autoload.php';
	if (is_file($candidateAutoload)) {
		$autoloadPath = $candidateAutoload;
		$basePath = $candidate;
		break;
	}
}

if (!$autoloadPath) {
	http_response_code(500);
	error_log('Bootstrap error: vendor/autoload.php not found. Checked: ' . implode(', ', $pathCandidates));
	echo 'Application bootstrap failed: dependencies are missing.';
	exit;
}

require $autoloadPath;

// Load environment variables from the detected base path (if present)
if (class_exists('Dotenv\\Dotenv') && is_file($basePath . '/.env')) {
	$dotenv = Dotenv\Dotenv::createImmutable($basePath);
	$dotenv->safeLoad();
}

$routesPath = $basePath . '/src/Routes/index.php';
if (!is_file($routesPath)) {
	http_response_code(500);
	error_log('Bootstrap error: routes file not found at ' . $routesPath);
	echo 'Application bootstrap failed: routes are missing.';
	exit;
}

require $routesPath;