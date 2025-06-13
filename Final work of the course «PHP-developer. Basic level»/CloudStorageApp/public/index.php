<?php

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_path', '/CloudStorageApp/public');
    ini_set('session.gc_lifetime', 3600);
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}


header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');


spl_autoload_register(function ($class) {
    $base_dir = __DIR__ . '/../';
    $file = $base_dir . preg_replace('#^App/#', '', str_replace('\\', '/', $class)) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Request;
use App\Core\Response;


$publicRoutes = ['/login', '/register', '/CloudStorageApp/public/', '/password-reset-public']; // Добавлен корневой маршрут
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isPublicRoute = false;

foreach ($publicRoutes as $route) {

    if ($currentPath === $route || substr($currentPath, -strlen($route)) === $route) {
        $isPublicRoute = true;
        break;
    }
}

if (!$isPublicRoute && !isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Пользователь не авторизован']);
    exit;
}

$urlList = [
    '/users/get/{id}' => ['GET' => ['App\Controllers\UserController', 'get']],
    '/users/current' => ['GET' => ['App\Controllers\UserController', 'getCurrentUser']],
    '/hello' => ['GET' => ['App\Controllers\UserController', 'hello']],
    '/users/list' => ['GET' => ['App\Controllers\UserController', 'list']],
    '/admin/users/list' => ['GET' => ['App\Controllers\AdminController', 'list']],
    '/admin/users/get/{id}' => ['GET' => ['App\Controllers\AdminController', 'get']],
    '/admin/users/delete/{id}' => ['DELETE' => ['App\Controllers\AdminController', 'delete']],
    '/admin/users/update/{id}' => ['PUT' => ['App\Controllers\AdminController', 'update']],
    '/admin/users/create' => ['POST' => ['App\Controllers\AdminController', 'create']],
    '/register' => ['POST' => ['App\Controllers\UserController', 'register']],
    '/login' => ['POST' => ['App\Controllers\UserController', 'login']],
    '/logout' => ['POST' => function () {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = array();

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }

        session_destroy();

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }],
    '/password-reset-public' => ['POST' => ['App\Controllers\UserController', 'publicPasswordReset']],
    '/files/upload' => ['POST' => ['App\Controllers\FileController', 'upload']],
    '/files/list' => ['GET' => ['App\Controllers\FileController', 'list']],
    '/files/get/{id}' => ['GET' => ['App\Controllers\FileController', 'get']],
    '/files/add' => ['POST' => ['App\Controllers\FileController', 'add']],
    '/files/rename' => ['PUT' => ['App\Controllers\FileController', 'rename']],
    '/files/remove/{id}' => ['DELETE' => ['App\Controllers\FileController', 'remove']],
    '/files/delete/{id}' => ['DELETE' => ['App\Controllers\FileController', 'delete']],
    '/files/share' => ['POST' => ['App\Controllers\FileController', 'share']],
    '/files/move' => ['PUT' => ['App\Controllers\FileController', 'move']],
    '/files/download/{id}' => ['GET' => ['App\Controllers\FileController', 'download']],
    '/directories/add' => ['POST' => ['App\Controllers\DirectoryController', 'add']],
    '/directories/create' => ['POST' => ['App\Controllers\DirectoryController', 'add']],

    '/files/info/{id}' => ['GET' => ['App\Controllers\FileController', 'getFileInfo']],
    '/directories/rename' => ['PUT' => ['App\Controllers\DirectoryController', 'rename']],
    '/directories/get/{id}' => ['GET' => ['App\Controllers\DirectoryController', 'get']],
    '/directories/download/{id}' => ['GET' => ['App\Controllers\DirectoryController', 'download']],
    '/directories/delete/{id}' => ['DELETE' => ['App\Controllers\DirectoryController', 'delete']],
    '/directories/share' => ['POST' => ['App\Controllers\DirectoryController', 'share']],
    '/directories/move' => ['PUT' => ['App\Controllers\DirectoryController', 'move']],
    '/directories/unshare' => ['POST' => ['App\Controllers\DirectoryController', 'unshare']],
    '/files/unshare' => ['POST' => ['App\Controllers\FileController', 'unshare']],

];


$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$route = '/' . ltrim(str_replace($scriptName, '', $requestUri), '/');
$route = rtrim($route, '/');
$method = $_SERVER['REQUEST_METHOD'];

function matchRoute($route, $urlList, &$params): array
{
    foreach ($urlList as $pattern => $methods) {

        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)}#', function ($matches) {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $pattern);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $route, $matches)) {
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return [$pattern, $methods];
        }
    }
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

    if (!is_array($handler) || count($handler) !== 2) {
        http_response_code(500);
        echo json_encode(["error" => "Invalid handler configuration"]);
        exit;
    }

    [$class, $action] = $handler;

    if (!class_exists($class)) {
        http_response_code(500);
        echo json_encode(["error" => "Class $class not found"]);
        exit;
    }
    if (!method_exists($class, $action)) {
        http_response_code(500);
        echo json_encode(["error" => "Method $action not found in $class"]);
        exit;
    }

    $controller = new $class();
    $request = new Request();

    $request->routeParams = $params;

    $requestMethod = strtoupper($method);

    if ($class === 'App\Controllers\FileController' && $action === 'download') {
        $controller->$action($request);
        exit;
    }
    if ($class === 'App\Controllers\DirectoryController' && $action === 'download') {
        $controller->$action($request);
        exit;
    }

    $response = $controller->$action($request);
    if ($response instanceof Response) {
        header('Content-Type: application/json');
        $response->send();
    }
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
