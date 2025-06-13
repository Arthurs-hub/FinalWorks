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
        $response = $router->processRequest($request);
        $response->send();
    }
}
