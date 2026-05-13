<?php

namespace App;

class Router
{
    protected $routes = [];

    private function addRoute($route, $controller, $action, $method)
    {

        $this->routes[$method][$route] = ['controller' => $controller, 'action' => $action];
    }

    public function get($route, $controller, $action)
    {
        $this->addRoute($route, $controller, $action, "GET");
    }

    public function post($route, $controller, $action)
    {
        $this->addRoute($route, $controller, $action, "POST");
    }

    public function dispatch(){
    $uri = strtok($_SERVER['REQUEST_URI'], '?');
    $method =  $_SERVER['REQUEST_METHOD'];

    // Normalize URI when app is hosted in a subdirectory (common on shared hosting).
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($scriptDir && $scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
        $uri = substr($uri, strlen($scriptDir));
        if ($uri === '' || $uri === false) {
            $uri = '/';
        }
    }

    // Exact match
    if (array_key_exists($uri, $this->routes[$method])) {
        $controller = $this->routes[$method][$uri]['controller'];
        $action = $this->routes[$method][$uri]['action'];

        $controller = new $controller();
        $controller->$action();
        return;
    }

    // Pattern match for /api/orders/{orderId}/capture
    if ($method === 'POST' && preg_match('#^/api/orders/([^/]+)/capture$#', $uri, $matches)) {
        $orderId = $matches[1];
        $controller = new \App\Controllers\OrderController();
        $controller->capturePaypalOrder($orderId);
        return;
    }

    throw new \Exception("No route found for URI: $uri");
}
}