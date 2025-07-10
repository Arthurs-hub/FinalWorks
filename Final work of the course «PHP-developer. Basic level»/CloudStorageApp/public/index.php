<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/CloudStorageApp',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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

header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');


if (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Edge') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'Edg/') !== false)) {
    header('X-UA-Compatible: IE=edge');
    header('Vary: User-Agent');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
}

$authMiddleware = new AuthMiddleware();

$publicRoutes = ['/login', '/register', '/', '/password-reset-public'];
$basePath = '/CloudStorageApp/public';
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($currentPath, $basePath) === 0) {
    $currentPath = substr($currentPath, strlen($basePath));
    if ($currentPath === '') {
        $currentPath = '/';
    }
}

$isPublicRoute = false;
foreach ($publicRoutes as $route) {
    if ($currentPath === $route || substr($currentPath, -strlen($route)) === $route) {
        $isPublicRoute = true;

        break;
    }
}

if (! $isPublicRoute) {
    $authMiddleware->requireAuth();
}

if (strpos($currentPath, '/admin') === 0) {
    $authMiddleware->requireAdmin();
}

function isAdmin(?int $userId): bool
{
    if (! $userId) {
        return false;
    }

    try {
        $userService = new \App\Services\UserService();

        return $userService->isAdmin($userId);
    } catch (\Exception $e) {
        Logger::error("Error checking admin status", ['user_id' => $userId, 'error' => $e->getMessage()]);

        return false;
    }
}


$urlList = [

    '/users/get/{id}' => ['GET' => ['App\Controllers\UserController', 'get']],
    '/users/current' => ['GET' => ['App\Controllers\UserController', 'getCurrentUser']],
    '/users/list' => ['GET' => ['App\Controllers\UserController', 'list']],
    '/users/update/{id}' => ['PUT' => ['App\Controllers\UserController', 'update']],
    '/users/delete/{id}' => ['DELETE' => ['App\Controllers\UserController', 'delete']],
    '/users/change-password' => ['POST' => ['App\Controllers\UserController', 'changePassword']],


    '/login' => ['POST' => ['App\Controllers\UserController', 'login']],
    '/register' => ['POST' => ['App\Controllers\UserController', 'register']],
    '/logout' => ['POST' => ['App\Controllers\UserController', 'logout']],
    '/password-reset-public' => ['POST' => ['App\Controllers\UserController', 'publicPasswordReset']],


    '/admin/dashboard' => ['GET' => ['App\Controllers\AdminController', 'dashboard']],
    '/admin/stats' => ['GET' => ['App\Controllers\AdminController', 'getStats']],
    '/admin/users' => [
        'GET' => ['App\Controllers\AdminController', 'getUsers'],
        'POST' => ['App\Controllers\AdminController', 'createUser'],
    ],
    '/admin/users/export' => ['GET' => ['App\Controllers\AdminController', 'exportUsers']],
    '/admin/users/create' => ['POST' => ['App\Controllers\AdminController', 'createUser']],
    '/admin/users/{id}' => [
        'GET' => ['App\Controllers\AdminController', 'getUserById'],
        'PUT' => ['App\Controllers\AdminController', 'updateUser'],
        'DELETE' => ['App\Controllers\AdminController', 'deleteUser'],
    ],

    '/admin/users/{id}/ban' => ['POST' => ['App\Controllers\AdminController', 'banUser']],
    '/admin/users/{id}/unban' => ['POST' => ['App\Controllers\AdminController', 'unbanUser']],
    '/admin/users/{id}/make-admin' => ['POST' => ['App\Controllers\AdminController', 'makeAdmin']],
    '/admin/users/{id}/remove-admin' => ['POST' => ['App\Controllers\AdminController', 'removeAdmin']],
    '/admin/users/bulk-delete' => ['POST' => ['App\Controllers\AdminController', 'bulkDeleteUsers']],
    '/admin/users/search' => ['GET' => ['App\Controllers\AdminController', 'searchUsers']],
    '/admin/files' => ['GET' => ['App\Controllers\AdminController', 'getFiles']],
    '/admin/files/cleanup' => ['POST' => ['App\Controllers\AdminController', 'cleanupFiles']],
    '/admin/files/clear' => ['DELETE' => ['App\Controllers\AdminController', 'clearFiles']],
    '/admin/files/{id}' => ['DELETE' => ['App\Controllers\AdminController', 'deleteFile']],
    '/admin/logs' => ['GET' => ['App\Controllers\AdminController', 'getLogs']],
    '/admin/logs/clear' => ['DELETE' => ['App\Controllers\AdminController', 'clearLogs']],
    '/admin/system/health' => ['GET' => ['App\Controllers\AdminController', 'getSystemHealth']],
    '/admin/security/report' => ['GET' => ['App\Controllers\AdminController', 'getSecurityReport']],

    '/files/list' => [
        'GET' => ['App\Controllers\FileController', 'list'],
        'POST' => ['App\Controllers\FileController', 'list'],
    ],
    '/files/upload' => ['POST' => ['App\Controllers\FileController', 'upload']],
    '/files/share' => ['POST' => ['App\Controllers\FileController', 'share']],
    '/files/rename' => ['PUT' => ['App\Controllers\FileController', 'rename']],
    '/files/remove/{id}' => ['DELETE' => ['App\Controllers\FileController', 'remove']],
    '/files/download/{id}' => ['GET' => ['App\Controllers\FileController', 'download']],
    '/files/unshare' => ['POST' => ['App\Controllers\FileController', 'unshare']],
    '/files/info/{id}' => ['GET' => ['App\Controllers\FileController', 'getFileInfo']],
    '/files/get/{id}' => ['GET' => ['App\Controllers\FileController', 'get']],
    '/files/add' => ['POST' => ['App\Controllers\FileController', 'add']],
    '/files/move' => ['PUT' => ['App\Controllers\FileController', 'move']],
    '/files/preview/{id}' => ['GET' => ['App\Controllers\FileController', 'preview']],
   

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
list($matchedPattern, $methods) = matchRoute($route, $urlList, $params);

if ($matchedPattern && isset($methods[$method])) {
    $handler = $methods[$method];

    if ($handler instanceof Closure) {
        $handler();
        exit;
    }

    if (! is_array($handler) || count($handler) !== 2) {
        http_response_code(500);
        echo json_encode(["error" => "Invalid handler configuration"]);
        exit;
    }

    [$class, $action] = $handler;

    if (! class_exists($class)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Class $class not found"]);
        exit;
    }
    if (! method_exists($class, $action)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Method $action not found in $class"]);
        exit;
    }

    try {
        $controller = new $class();
        $request = new Request();
        $request->routeParams = $params;

        if ($class === 'App\Controllers\FileController' && $action === 'download') {
            $controller->$action($request);
            exit;
        }

        $response = $controller->$action($request);
        if ($response instanceof Response) {
            header('Content-Type: application/json');
            $response->send();
        }
    } catch (\InvalidArgumentException $e) {

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (\Exception $e) {
        Logger::error("Unhandled exception in controller", [
            'class' => $class,
            'action' => $action,
            'error' => $e->getMessage(),
        ]);

        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Внутренняя ошибка сервера']);
    }

    $executionTime = microtime(true) - $startTime;
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $responseCode = http_response_code() ?: 200;

    Logger::accessLog($method, $uri, $responseCode, $executionTime);
    exit;
} else {
    echo '<link rel="preload" href="CoveringCloudIcon.png" as="image">';
    http_response_code(404);
    echo '<div style="text-align:center;">
    <div style="position: relative; height: 300px;">
        <img src="CoveringCloudIcon.png" alt="Облако" style="
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            animation: cloudFloat 2s ease-out forwards;
            z-index: 1;
        ">
        <div style="
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 0;
            z-index: 2;
        ">
            <h3 style="font-size: 18px;">Добро пожаловать в CloudStorageApp!</h3>
            <p style="font-size: 18px;">
                Нажмите <a href="/CloudStorageApp/public/login.html">здесь</a>, чтобы перейти на страницу входа.
            </p>
        </div>
    </div>
</div>
<style>
@keyframes cloudFloat {
    from {
        top: -100px;
    }
    to {
        top: 50px;
    }
}
</style>';
}
