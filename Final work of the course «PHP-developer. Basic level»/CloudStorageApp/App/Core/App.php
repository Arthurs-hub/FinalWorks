<?php

namespace App\Core;

class App
{
    public function __construct()
    {
        spl_autoload_register([$this, 'autoload']);
    }

    public function autoload($class)
    {
        $prefix = 'App\\';
        $base_dir = __DIR__ . '/../';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }

    public function run()
    {
        $request = new Request();
        $router = new Router();

        $uri = $request->getUri();
        $method = $request->getMethod();

        $params = [];
        $match = $router->matchRoute($uri, $method, $params);

        if ($match === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Route not found']);
            exit;
        }

        [$route, $handler] = $match;
        [$controllerClass, $methodName] = $handler;

        $controller = new $controllerClass();

        $request->routeParams = $params;

        $response = $controller->$methodName($request);

        if ($response instanceof Response) {
            $response->send();
        } else {
            header('Content-Type: application/json');
            echo json_encode($response);
        }
    }
}
