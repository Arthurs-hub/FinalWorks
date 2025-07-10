<?php

namespace App\Core;

class Router
{
    private array $routes = [

        '/users/list' => ['GET' => ['App\Controllers\UserController', 'list']],
        '/users/get/{id}' => ['GET' => ['App\Controllers\UserController', 'get']],
        '/users/update/{id}' => ['PUT' => ['App\Controllers\UserController', 'update']],
        '/users/login' => ['POST' => ['App\Controllers\UserController', 'login']],
        '/users/logout' => ['GET' => ['App\Controllers\UserController', 'logout']],
        '/users/reset_password' => ['GET' => ['App\Controllers\UserController', 'resetPassword']],
        '/users/current' => ['GET' => ['App\Controllers\UserController', 'getCurrentUser']],

        '/admin/users/list' => ['GET' => ['App\Controllers\AdminController', 'list']],
        '/admin/users/get/{id}' => ['GET' => ['App\Controllers\AdminController', 'get']],
        '/admin/users/delete/{id}' => ['DELETE' => ['App\Controllers\AdminController', 'delete']],
        '/admin/users/update/{id}' => ['PUT' => ['App\Controllers\AdminController', 'update']],
        '/admin/users/create' => ['POST' => ['App\Controllers\AdminController', 'create']],

        '/files/list' => [
            'GET' => ['App\Controllers\FileController', 'list'],
            'POST' => ['App\Controllers\FileController', 'list'],
        ],
        '/files/get/{id}' => ['GET' => ['App\Controllers\FileController', 'get']],
        '/files/add' => ['POST' => ['App\Controllers\FileController', 'add']],
        '/files/rename' => ['PUT' => ['App\Controllers\FileController', 'rename']],
        '/files/remove/{id}' => ['DELETE' => ['App\Controllers\FileController', 'remove']],
        '/files/share' => ['POST' => ['App\Controllers\FileController', 'share']],
        '/files/upload' => ['POST' => ['App\Controllers\FileController', 'upload']],
        '/files/info/{id}' => ['GET' => ['App\Controllers\FileController', 'getFileInfo']],
        '/files/preview' => ['GET' => ['App\Controllers\FileController', 'preview']],
        '/files/preview/{id}' => ['GET' => ['App\Controllers\FileController', 'preview']],
        '/files/download/{id}' => ['GET' => ['App\Controllers\FileController', 'download']],
        '/files/unshare' => ['POST' => ['App\Controllers\FileController', 'unshare']],
        '/files/move' => ['PUT' => ['App\Controllers\FileController', 'move']],
        '/files/{id}' => ['GET' => ['App\Controllers\FileController', 'getFileInfo']],


        '/directories/share' => ['POST' => ['App\Controllers\DirectoryController', 'share']],
        '/directories/unshare' => ['POST' => ['App\Controllers\DirectoryController', 'unshare']],
        '/directories/add' => ['POST' => ['App\Controllers\DirectoryController', 'add']],
        '/directories/get/{id}' => ['GET' => ['App\Controllers\DirectoryController', 'get']],
        '/directories/rename' => ['PUT' => ['App\Controllers\DirectoryController', 'rename']],
        '/directories/move' => ['PUT' => ['App\Controllers\DirectoryController', 'move']],
        '/directories/delete/{id}' => ['DELETE' => ['App\Controllers\DirectoryController', 'delete']],
        '/directories/download/{id}' => ['GET' => ['App\Controllers\DirectoryController', 'download']],

        '/admin/users' => ['GET' => ['App\Controllers\AdminController', 'getUsers']],
        '/admin/users/{id}' => ['DELETE' => ['App\Controllers\AdminController', 'deleteUser']],
        '/admin/users/{id}/toggle-status' => ['PUT' => ['App\Controllers\AdminController', 'toggleUserStatus']],

        '/hello' => ['GET' => ['App\Controllers\UserController', 'hello']],
    ];

    public function processRequest(Request $request): Response
    {
        $uri = $request->getRoute();
        $method = $request->getMethod();

        foreach ($this->routes as $route => $methods) {

            $regex = '#^' . preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)}#', function ($matches) {
                return '(?P<' . $matches[1] . '>[^/]+)';
            }, $route) . '$#';

            if (preg_match($regex, $uri, $matches) && isset($methods[$method])) {

                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                $request->routeParams = $params;

                [$controller, $action] = $methods[$method];
                $ctrl = new $controller();

                return $ctrl->$action($request);
            }
        }

        return new Response(['error' => 'Not found'], 404);
    }
}
