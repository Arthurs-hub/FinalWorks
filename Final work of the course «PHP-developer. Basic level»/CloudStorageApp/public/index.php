<?php

if (session_status() === PHP_SESSION_NONE) {
    $config = require __DIR__ . '/../config/config.php';

    session_set_cookie_params([
        'lifetime' => $config['security']['session_lifetime'],
        'path' => '/CloudStorageApp',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}


$startTime = microtime(true);

spl_autoload_register(function ($class) {
    $base_dir = __DIR__ . '/../';
    $file = $base_dir . preg_replace('#^App/#', '', str_replace('\\', '/', $class)) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\AuthMiddleware;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

Logger::info("Request started", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'user_id' => $_SESSION['user_id'] ?? null,
]);


error_log("Incoming request URI: " . $_SERVER['REQUEST_URI']);
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'none'));

$urlList = [

    '/register' => ['POST' => ['App\Controllers\UserController', 'register']],
    '/login' => ['POST' => ['App\Controllers\UserController', 'login']],
    '/logout' => ['GET' => ['App\Controllers\UserController', 'logout']],
    '/reset_password' => ['POST' => ['App\Controllers\UserController', 'publicPasswordReset']], // Основной роут как указал куратор
    '/create-first-admin' => ['POST' => ['App\Controllers\UserController', 'createFirstAdmin']],


    '/password-reset-request' => ['POST' => ['App\Controllers\UserController', 'requestPasswordReset']],
    '/password-reset-confirm' => ['POST' => ['App\Controllers\UserController', 'resetPasswordWithToken']],
    '/password-reset-validate' => ['POST' => ['App\Controllers\UserController', 'validateResetToken']],


    '/admin/logs' => ['GET' => ['App\Controllers\AdminController', 'getLogs']],
    '/admin/logs/clear' => ['DELETE' => ['App\Controllers\AdminController', 'clearLogs']],
    '/admin/security/report' => ['GET' => ['App\Controllers\AdminController', 'getSecurityReport']],


    '/users/current' => ['GET' => ['App\Controllers\UserController', 'getCurrentUser']],
    '/users/list' => ['GET' => ['App\Controllers\UserController', 'list']],
    '/users/get/{id}' => ['GET' => ['App\Controllers\UserController', 'get']],
    '/users/update' => ['PUT' => ['App\Controllers\UserController', 'update']],
    '/users/update/{id}' => ['PUT' => ['App\Controllers\UserController', 'update']],
    '/users/stats' => ['GET' => ['App\Controllers\UserController', 'getUserStats']],
    '/users/stats/{id}' => ['GET' => ['App\Controllers\UserController', 'getUserStats']],
    '/users/change-password' => ['PUT' => ['App\Controllers\UserController', 'changePassword']],


    '/admin/users/bulk-delete' => ['DELETE' => ['App\Controllers\AdminController', 'bulkDeleteUsers']],
    '/admin/stats' => ['GET' => ['App\Controllers\AdminController', 'getStats']],
    '/admin/users' => ['GET' => ['App\Controllers\AdminController', 'getUsers']],
    '/admin/users/list' => ['GET' => ['App\Controllers\AdminController', 'getUsers']],
    '/admin/users/export' => ['GET' => ['App\Controllers\AdminController', 'exportUsers']],
    '/admin/users/export/download' => ['GET' => ['App\Controllers\AdminController', 'downloadUsersExport']],
    '/admin/files' => ['GET' => ['App\Controllers\AdminController', 'getFiles']],
    '/admin/files/list' => ['GET' => ['App\Controllers\AdminController', 'getFiles']],
    '/admin/files/cleanup' => ['DELETE' => ['App\Controllers\AdminController', 'cleanupFiles']],
    '/admin/files/clear' => ['DELETE' => ['App\Controllers\AdminController', 'clearFiles']],
    '/admin/files/{id}' => ['DELETE' => ['App\Controllers\AdminController', 'deleteFile']],
    '/admin/users/{id}' => [
        'GET' => ['App\Controllers\AdminController', 'getUser'],
        'PUT' => ['App\Controllers\AdminController', 'updateUser'],
        'DELETE' => ['App\Controllers\AdminController', 'deleteUser']
    ],
    '/admin/users/update/{id}' => ['PUT' => ['App\Controllers\AdminController', 'updateUser']],
    '/admin/users/{id}/make-admin' => ['POST' => ['App\Controllers\AdminController', 'makeAdmin']],
    '/admin/users/{id}/remove-admin' => ['PATCH' => ['App\Controllers\AdminController', 'removeAdmin']],
    '/admin/users/search' => ['GET' => ['App\Controllers\AdminController', 'searchUsers']],
    '/admin/system/health' => ['GET' => ['App\Controllers\AdminController', 'getSystemHealth']],
    '/admin/directories/delete/{id}' => ['DELETE' => ['App\Controllers\DirectoryController', 'delete']],


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


    '/hello' => ['GET' => ['App\Controllers\UserController', 'hello']],
];

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$route = '/' . ltrim(str_replace($scriptName, '', $requestUri), '/');
$route = rtrim($route, '/');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Parsed route: " . $route);
error_log("Request method: " . $method);

function matchRoute($route, $urlList, &$params): array
{
    foreach ($urlList as $pattern => $methods) {
        error_log("Checking route pattern: $pattern");
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)}#', function ($matches) {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $route, $matches)) {
            error_log("Matched pattern: $pattern");
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return [$pattern, $methods];
        }
    }
    error_log("No matching route found for: $route");

    return [null, null];
}

$params = [];
[$matchedPattern, $methods] = matchRoute($route, $urlList, $params);

if ($matchedPattern === null || !isset($methods[$method])) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Роут не найден: ' . $route]);
    exit;
}

[$controllerClass, $methodName] = $methods[$method];

$publicRoutes = [
    '/register',
    '/login',
    '/reset_password',
    '/create-first-admin',
    '/password-reset-request',
    '/password-reset-confirm',
    '/password-reset-validate'
];
$adminRoutes = [
    '/admin/'

];

$isPublicRoute = in_array($route, $publicRoutes);
$isAdminRoute = false;

foreach ($adminRoutes as $adminPrefix) {
    if (strpos($route, $adminPrefix) === 0) {
        $isAdminRoute = true;
        break;
    }
}

if (!$isPublicRoute) {
    AuthMiddleware::requireAuth();
}

if ($isAdminRoute) {
    AuthMiddleware::requireAdmin();
}

$request = new Request();
$request->routeParams = $params;

try {
    $controller = new $controllerClass();
    $response = $controller->$methodName($request);

    if ($response instanceof Response) {
        $response->send();
    } else {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($response);
    }
} catch (Exception $e) {
    error_log("Controller error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка сервера: ' . $e->getMessage()
    ]);
}

$endTime = microtime(true);
$executionTime = ($endTime - $startTime) * 1000; // в миллисекундах

Logger::info("Request completed", [
    'method' => $method,
    'route' => $route,
    'execution_time_ms' => round($executionTime, 2),
    'user_id' => $_SESSION['user_id'] ?? null,
]);
