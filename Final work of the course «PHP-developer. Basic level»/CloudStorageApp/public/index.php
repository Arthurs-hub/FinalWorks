<?php

declare(strict_types=1);



require_once __DIR__ . '/../vendor/autoload.php';

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma, Expires");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
// --- Конец блока CORS ---

$config = require __DIR__ . '/../config/config.php';

date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

use App\Core\AuthMiddleware;
use App\Core\Container;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => $config['security']['session_lifetime'],
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

$startTime = microtime(true);

Logger::info('Request started', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'uri' => $_SERVER['REQUEST_URI'] ?? null,
    'user_id' => $_SESSION['user_id'] ?? null,
]);

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$route = rtrim($requestUri, '/');
if (empty($route)) {
    $route = '/';
}

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

$container = new Container();

// Bind repositories
$container->bind(App\Repositories\IUserRepository::class, function ($c) {
    return new App\Repositories\UserRepository(
        $c->make(App\Core\Db::class),
        $c->make(App\Repositories\IDirectoryRepository::class)
    );
});
$container->bind(App\Repositories\IPasswordResetRepository::class, function ($c) {
    return new App\Repositories\PasswordResetRepository($c->make(App\Core\Db::class));
});
$container->bind(App\Repositories\IAdminRepository::class, function ($c) {
    return new App\Repositories\AdminRepository(
        $c->make(App\Core\Db::class),
        $c->make(App\Repositories\IUserRepository::class) // or UserRepository::class if you bind concrete class
    );
});
$container->bind(App\Repositories\IDirectoryRepository::class, function ($c) {
    return new App\Repositories\DirectoryRepository($c->make(App\Core\Db::class));
});
$container->bind(App\Repositories\IFileRepository::class, function ($c) {
    return new App\Repositories\FileRepository($c->make(App\Core\Db::class));
});
$container->bind(App\Repositories\ITwoFactorRepository::class, function ($c) {
    return new App\Repositories\TwoFactorRepository($c->make(App\Core\Db::class));
});

// Bind services
$container->bind(App\Services\IEmailService::class, App\Services\EmailService::class);
$container->bind(App\Services\ITwoFactorService::class, App\Services\TwoFactorService::class);
$container->bind(App\Services\IDirectoryService::class, App\Services\DirectoryService::class);
$container->bind(App\Services\IFileService::class, App\Services\FileService::class);
$container->bind(App\Services\FileResponseService::class);
$container->bind(App\Services\FileTypeService::class);

$container->singleton(App\Core\Db::class, function () use ($config) {
    return new App\Core\Db($config['database']);
});

// Bind UserService with dependencies and config
$container->bind(App\Services\IUserService::class, function ($c) use ($config) {
    return new App\Services\UserService(
        $c->make(App\Repositories\IUserRepository::class),
        $c->make(App\Repositories\IPasswordResetRepository::class),
        $c->make(App\Services\IEmailService::class),
        $c->make(App\Repositories\ITwoFactorRepository::class),
        $c->make(App\Services\ITwoFactorService::class),
        $c->make(App\Repositories\IAdminRepository::class),
        $c->make(App\Repositories\IDirectoryRepository::class),
        $c->make(App\Core\Db::class),
        $config
    );
});

// Bind AdminService
$container->bind(App\Services\AdminService::class, function ($c) use ($config) {
    return new App\Services\AdminService(
        $c->make(App\Repositories\IUserRepository::class),
        $c->make(App\Repositories\IAdminRepository::class),
        $c->make(App\Services\IUserService::class),
        $c->make(App\Core\Db::class),
        $config
    );
});


AuthMiddleware::setUserService($container->make(App\Services\IUserService::class));

$publicRoutes = [
    '/users/register',
    '/users/login',
    '/users/reset_password',
    '/users/create-first-admin',
    '/users/password-reset-request',
    '/users/password-reset-confirm',
    '/users/password-reset-validate',
    '/api/2fa/send-email-code',
    '/api/2fa/verify-email-login',
    '/api/2fa/verify-totp-login',
    '/api/2fa/verify-backup-code',
];
$adminRoutes = [
    '/admin/',
];

$isPublicRoute = in_array($route, $publicRoutes, true);
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
    $controller = $container->make($controllerClass);

    $response = $controller->$methodName($request);

    if ($response instanceof Response) {
        $response->send();
    } else {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($response);
    }
} catch (Exception $e) {
    error_log('Controller error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка сервера: ' . $e->getMessage(),
    ]);
}

$endTime = microtime(true);
$executionTime = ($endTime - $startTime) * 1000; // в миллисекундах

Logger::info('Request completed', [
    'method' => $method,
    'route' => $route,
    'execution_time_ms' => round($executionTime, 2),
    'user_id' => $_SESSION['user_id'] ?? null,
]);
