<?php

namespace App;

class Router
{
    protected array $routes = [];

    private function addRoute($route, $controller, $action, $method)
    {
        $this->routes[$method][$route] = [
            'controller' => $controller,
            'action' => $action
        ];
    }

    public function get($route, $controller, $action)
    {
        $this->addRoute($route, $controller, $action, "GET");
    }

    public function post($route, $controller, $action)
    {
        $this->addRoute($route, $controller, $action, "POST");
    }

    private function getCleanUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // remove query string
        $uri = parse_url($uri, PHP_URL_PATH);

        // remove index.php anywhere in path
        $uri = str_replace('/index.php', '', $uri);

        // normalize slashes
        $uri = preg_replace('#/+#', '/', $uri);

        // remove trailing slash
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        return $uri ?: '/';
    }
    
    public function dispatch()
{
    try {
        $uri = $this->getCleanUri();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $routes = $this->routes[$method] ?? [];

        if (isset($routes[$uri])) {
            $controller = $routes[$uri]['controller'];
            $action = $routes[$uri]['action'];

            $instance = new $controller();
            $instance->$action();
            return;
        }

        http_response_code(404);
        echo "404 Not Found";
    } catch (\Throwable $e) {
        http_response_code(500);
        echo "<pre>";
        echo $e->getMessage() . "\n";
        echo $e->getFile() . ":" . $e->getLine();
        echo "</pre>";
        exit;
    }
}

    // public function dispatch()
    // {
    //     $uri = $this->getCleanUri();
    //     $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    //     $routes = $this->routes[$method] ?? [];

    //     // 1. Exact match
    //     if (isset($routes[$uri])) {
    //         $controller = $routes[$uri]['controller'];
    //         $action = $routes[$uri]['action'];

    //         $instance = new $controller();
    //         $instance->$action();
    //         return;
    //     }

    //     // 2. Pattern match (safe dynamic route)
    //     foreach ($routes as $route => $data) {
    //         $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $route);

    //         if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {

    //             array_shift($matches);

    //             $controller = $data['controller'];
    //             $action = $data['action'];

    //             $instance = new $controller();

    //             // pass params if needed
    //             $instance->$action(...$matches);

    //             return;
    //         }
    //     }

    //     // 3. 404 fallback (NO EXCEPTION = safer in production)
    //     http_response_code(404);
    //     echo "404 Not Found";
    // }
}