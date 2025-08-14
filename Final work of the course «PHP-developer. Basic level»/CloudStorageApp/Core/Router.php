<?php

namespace App\Core;

class Router
{
    private array $routes = [
        // User routes
        '/users/list' => ['GET' => ['App\Controllers\UserController', 'list']],
        '/users/get/{id}' => ['GET' => ['App\Controllers\UserController', 'get']],
        '/users/update' => ['PUT' => ['App\Controllers\UserController', 'update']],
        '/users/update/{id}' => ['PUT' => ['App\Controllers\UserController', 'update']],
        '/users/current' => ['GET' => ['App\Controllers\UserController', 'getCurrentUser']],
        '/users/stats' => ['GET' => ['App\Controllers\UserController', 'getUserStats']],
        '/users/stats/{id}' => ['GET' => ['App\Controllers\UserController', 'getUserStats']],
        '/users/change-password' => ['PUT' => ['App\Controllers\UserController', 'changePassword']],
        '/users/login' => ['POST' => ['App\Controllers\UserController', 'login']],
        '/users/logout' => ['GET' => ['App\Controllers\UserController', 'logout']],
        '/users/reset_password' => ['POST' => ['App\Controllers\UserController', 'publicPasswordReset']],
        '/users/register' => ['POST' => ['App\Controllers\UserController', 'register']],
        '/users/create-first-admin' => ['POST' => ['App\Controllers\UserController', 'createFirstAdmin']],
        '/users/password-reset-request' => ['POST' => ['App\Controllers\UserController', 'requestPasswordReset']],
        '/users/password-reset-confirm' => ['POST' => ['App\Controllers\UserController', 'resetPasswordWithToken']],
        '/users/password-reset-validate' => ['POST' => ['App\Controllers\UserController', 'validateResetToken']],

        // Admin user routes 
        '/admin/users/list' => ['GET' => ['App\Controllers\AdminController', 'getUsers']],
        '/admin/users/get/{id}' => ['GET' => ['App\Controllers\AdminController', 'getUser']],
        '/admin/users/update/{id}' => ['PUT' => ['App\Controllers\AdminController', 'updateUser']],
        '/admin/users/delete/{id}' => ['DELETE' => ['App\Controllers\AdminController', 'deleteUser']],
        '/admin/users/create' => ['POST' => ['App\Controllers\AdminController', 'createUser']],
        '/admin/users/export' => ['GET' => ['App\Controllers\AdminController', 'exportUsers']],
        '/admin/users/export/download' => ['GET' => ['App\Controllers\AdminController', 'downloadUsersExport']],
        '/admin/users/bulk-delete' => ['DELETE' => ['App\Controllers\AdminController', 'bulkDeleteUsers']],
        '/admin/users/{id}/make-admin' => ['POST' => ['App\Controllers\AdminController', 'makeAdmin']],
        '/admin/users/{id}/remove-admin' => ['PATCH' => ['App\Controllers\AdminController', 'removeAdmin']],
        '/admin/users/search' => ['GET' => ['App\Controllers\AdminController', 'searchUsers']],

        // Admin logs and reports
        '/admin/logs' => ['GET' => ['App\Controllers\AdminController', 'getLogs']],
        '/admin/logs/clear' => ['DELETE' => ['App\Controllers\AdminController', 'clearLogs']],
        '/admin/security/report' => ['GET' => ['App\Controllers\AdminController', 'getSecurityReport']],
        '/admin/stats' => ['GET' => ['App\Controllers\AdminController', 'getStats']],

        // Admin files routes
        '/admin/files/list' => ['GET' => ['App\Controllers\AdminController', 'getFiles']],
        '/admin/files' => ['GET' => ['App\Controllers\AdminController', 'getFiles']],
        '/admin/files/cleanup' => ['DELETE' => ['App\Controllers\AdminController', 'cleanupFiles']],
        '/admin/files/clear' => ['DELETE' => ['App\Controllers\AdminController', 'clearFiles']],
        '/admin/files/{id}' => ['DELETE' => ['App\Controllers\AdminController', 'deleteFile']],

        // Admin system health
        '/admin/system/health' => ['GET' => ['App\Controllers\AdminController', 'getSystemHealth']],

        // Admin directories routes
        '/admin/directories/delete/{id}' => ['DELETE' => ['App\Controllers\DirectoryController', 'delete']],

        // File routes
        '/files/list' => [
            'GET' => ['App\Controllers\FileController', 'list'],
            'POST' => ['App\Controllers\FileController', 'list'],
        ],
        '/files/upload' => ['POST' => ['App\Controllers\FileController', 'upload']],
        '/files/share' => ['POST' => ['App\Controllers\FileController', 'share']],
        '/files/share/{id}/{user_id}' => [
            'PUT' => ['App\Controllers\FileController', 'shareWithUser'],
            'DELETE' => ['App\Controllers\FileController', 'unshareFromUser'],
        ],
        '/files/share/{id}' => ['GET' => ['App\Controllers\FileController', 'getShares']],
        '/files/rename' => ['PUT' => ['App\Controllers\FileController', 'rename']],
        '/files/remove/{id}' => ['DELETE' => ['App\Controllers\FileController', 'remove']],
        '/files/download/{id}' => ['GET' => ['App\Controllers\FileController', 'download']],
        '/files/unshare' => ['POST' => ['App\Controllers\FileController', 'unshare']],
        '/files/info/{id}' => ['GET' => ['App\Controllers\FileController', 'getFileInfo']],
        '/files/get/{id}' => ['GET' => ['App\Controllers\FileController', 'get']],
        '/files/add' => ['POST' => ['App\Controllers\FileController', 'add']],
        '/files/move' => ['PUT' => ['App\Controllers\FileController', 'move']],
        '/files/preview/{id}' => ['GET' => ['App\Controllers\FileController', 'preview']],
        '/files/search' => ['GET' => ['App\Controllers\FileController', 'searchFiles']],
        '/files/bulk-delete' => ['DELETE' => ['App\Controllers\FileController', 'bulkDelete']],
        '/files/shared' => ['GET' => ['App\Controllers\FileController', 'getSharedFiles']],

        // Directory routes
        '/directories/get/{id}' => ['GET' => ['App\Controllers\DirectoryController', 'get']],
        '/directories/share' => ['POST' => ['App\Controllers\DirectoryController', 'share']],
        '/directories/add' => ['POST' => ['App\Controllers\DirectoryController', 'add']],
        '/directories/move' => ['PUT' => ['App\Controllers\DirectoryController', 'move']],
        '/directories/rename' => ['PUT' => ['App\Controllers\DirectoryController', 'rename']],
        '/directories/upload' => ['POST' => ['App\Controllers\DirectoryController', 'upload']],
        '/directories/unshare' => ['POST' => ['App\Controllers\DirectoryController', 'unshare']],
        '/directories/delete/{id}' => ['DELETE' => ['App\Controllers\DirectoryController', 'delete']],
        '/directories/download/{id}' => ['GET' => ['App\Controllers\DirectoryController', 'download']],
        '/directories/list' => ['GET' => ['App\Controllers\DirectoryController', 'list']],
        '/directories/shared' => ['GET' => ['App\Controllers\DirectoryController', 'getSharedDirectories']],

        // Misc
        '/hello' => ['GET' => ['App\Controllers\UserController', 'hello']],
    ];

    public function matchRoute(string $uri, string $method, array &$params = []): ?array
    {
        foreach ($this->routes as $route => $methods) {
            $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)}#', function ($matches) {
                if ($matches[1] === 'id') {
                    return '(?P<' . $matches[1] . '>(root|\d+))';
                }
                return '(?P<' . $matches[1] . '>[^/]+)';
            }, $route);

            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $uri, $matches)) {
                if (!isset($methods[$method])) {
                    return null; 
                }

                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                return [$route, $methods[$method]];
            }
        }

        return null; 
    }
}
