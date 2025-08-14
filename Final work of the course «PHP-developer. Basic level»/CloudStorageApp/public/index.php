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
use App\Core\Router;

Logger::info("Request started", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'user_id' => $_SESSION['user_id'] ?? null,
]);

error_log("Incoming request URI: " . $_SERVER['REQUEST_URI']);
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'none'));

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$route = rtrim($requestUri, '/');

error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Parsed route: " . $route);
error_log("Request method: " . $method);

$router = new Router();

$params = [];
$match = $router->matchRoute($route, $method, $params);

if ($match === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Роут не найден: ' . $route]);
    exit;
}

[$matchedPattern, $handler] = $match;
[$controllerClass, $methodName] = $handler;

$publicRoutes = [
    '/users/register',
    '/users/login',
    '/users/reset_password',
    '/users/create-first-admin',
    '/users/password-reset-request',
    '/users/password-reset-confirm',
    '/users/password-reset-validate'
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

    if ($controllerClass === 'App\Controllers\FileController') {

        $directoryService = new App\Services\DirectoryService(new App\Repositories\DirectoryRepository());
        $fileService = new App\Services\FileService($directoryService);
        $controller = new $controllerClass($fileService, $directoryService);
    } elseif ($controllerClass === 'App\Controllers\DirectoryController') {
        $directoryRepository = new App\Repositories\DirectoryRepository();
        $directoryService = new App\Services\DirectoryService($directoryRepository);
        $controller = new $controllerClass($directoryService);
    } elseif ($controllerClass === 'App\Controllers\UserController') {

        $userRepository = new App\Repositories\UserRepository();
        $userService = new App\Services\UserService();
        $controller = new $controllerClass($userService);
    } else {

        $controller = new $controllerClass();
    }

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
