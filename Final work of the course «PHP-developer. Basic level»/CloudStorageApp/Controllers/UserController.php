<?php

namespace App\Controllers;


use App\Core\Request;
use App\Core\Response;
use App\Services\UserService;
use Exception;
use RuntimeException;
use PDO;

class UserController
{
    private UserService $userService;


    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function register(Request $request): Response
    {
        try {
            $data = $request->getData();

            $errors = $this->userService->validateUserData($data);
            if (!empty($errors)) {
                return new Response(['success' => false, 'error' => implode(', ', $errors)], 400);
            }

            $newUserId = $this->userService->createUserWithRootDirectory($data);

            return new Response(['success' => true, 'message' => 'Регистрация успешна!']);
        } catch (RuntimeException $e) {
            return new Response(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при регистрации'], 500);
        }
    }

    public function login(Request $request): Response
    {
        try {
            $data = $request->getData();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($email) || empty($password)) {
                return new Response(['success' => false, 'error' => 'Email и пароль обязательны'], 400);
            }

            $user = $this->userService->authenticateUser($email, $password);

            if ($user) {

                if (isset($user['role']) && strtolower($user['role']) === 'admin') {
                    $user['is_admin'] = 1;
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];

                return new Response([
                    'success' => true,
                    'role' => $user['role'] ?? null,
                    'user' => $user
                ]);
            } else {
                return new Response(['success' => false, 'error' => 'Неверный email или пароль'], 401);
            }
        } catch (Exception $e) {
            error_log("UserController::login exception: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка сервера'], 500);
        }
    }

    public function getCurrentUser(): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $user = $this->userService->getUserById($_SESSION['user_id']);

            if ($user) {

                $_SESSION['role'] = $user['role'];

                $responseUser = [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role'] ?? 'user',
                    'is_admin' => (int)($user['is_admin'] ?? 0),
                    'age' => $user['age'] ?? null,
                    'gender' => $user['gender'] ?? null,
                    'created_at' => $user['created_at'] ?? null,
                    'last_login' => $user['last_login'] ?? null,
                ];

                return new Response([
                    'success' => true,
                    'user' => $responseUser
                ]);
            } else {
                return new Response(['success' => false, 'error' => 'Пользователь не найден'], 404);
            }
        } catch (Exception $e) {
            error_log("UserController::getCurrentUser exception: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка сервера'], 500);
        }
    }

    public function get(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $request->routeParams['id'] ?? $_SESSION['user_id'];
            $user = $this->userService->getUserById($userId);

            if (!$user) {
                return new Response(['success' => false, 'error' => 'Пользователь не найден'], 404);
            }

            return new Response(['success' => true, 'user' => $user]);
        } catch (Exception $e) {
            return new Response(['success' => false, 'error' => 'Ошибка при получении данных пользователя'], 500);
        }
    }

    public function list(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $users = $this->userService->getAllUsers();
            return new Response(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            return new Response(['success' => false, 'error' => 'Ошибка при получении списка пользователей'], 500);
        }
    }

    public function update(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $data = $request->getData();
            $userId = $request->routeParams['id'] ?? $_SESSION['user_id'];

            if ($userId != $_SESSION['user_id'] && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
                return new Response(['success' => false, 'error' => 'Недостаточно прав'], 403);
            }

            $errors = $this->userService->validateUserData($data, true);
            if (!empty($errors)) {
                return new Response(['success' => false, 'error' => implode(', ', $errors)], 400);
            }

            $success = $this->userService->updateUser($userId, $data);
            if (!$success) {
                return new Response(['success' => false, 'error' => 'Ошибка при обновлении пользователя'], 500);
            }

            return new Response(['success' => true, 'message' => 'Пользователь успешно обновлен']);
        } catch (Exception $e) {
            return new Response(['success' => false, 'error' => 'Ошибка при обновлении пользователя'], 500);
        }
    }

    public function changePassword(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $data = $request->getData();
            $currentPassword = $data['current_password'] ?? '';
            $newPassword = $data['new_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword)) {
                return new Response(['success' => false, 'error' => 'Необходимо указать текущий и новый пароль'], 400);
            }

            $this->userService->changePassword($_SESSION['user_id'], $currentPassword, $newPassword);

            return new Response(['success' => true, 'message' => 'Пароль успешно изменен']);
        } catch (RuntimeException $e) {
            return new Response(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при изменении пароля'], 500);
        }
    }

    public function logout(): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = array();

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }

        session_destroy();

        return new Response(['success' => true, 'message' => 'Выход выполнен успешно']);
    }

    public function publicPasswordReset(Request $request): Response
    {
        try {
            $data = $request->getData();

            if (empty($data['email'])) {
                return new Response(['success' => false, 'error' => 'Необходимо указать email'], 400);
            }

            $tempPassword = $this->userService->resetPassword($data['email']);

            return new Response([
                'success' => true,
                'message' => 'Пароль сброшен',
                'temp_password' => $tempPassword
            ]);
        } catch (RuntimeException $e) {

            if (strpos($e->getMessage(), 'не найден') !== false) {
                return new Response(['success' => true, 'message' => 'Если пользователь существует, инструкции отправлены']);
            }
            return new Response(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при сбросе пароля'], 500);
        }
    }

    public function getUserStats(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $request->routeParams['id'] ?? $_SESSION['user_id'];

            if ($userId != $_SESSION['user_id'] && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
                return new Response(['success' => false, 'error' => 'Недостаточно прав'], 403);
            }

            $stats = $this->userService->getUserStats($userId);
            return new Response(['success' => true, 'stats' => $stats]);
        } catch (Exception $e) {
            return new Response(['success' => false, 'error' => 'Ошибка при получении статистики'], 500);
        }
    }

    public function delete(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            return new Response(['success' => false, 'error' => 'Недостаточно прав'], 403);
        }

        try {
            $userId = $request->routeParams['id'] ?? null;

            if (!$userId) {
                return new Response(['success' => false, 'error' => 'ID пользователя не указан'], 400);
            }

            if ($userId == $_SESSION['user_id']) {
                return new Response(['success' => false, 'error' => 'Нельзя удалить самого себя'], 400);
            }

            $success = $this->userService->deleteUser($userId);

            if ($success) {
                return new Response(['success' => true, 'message' => 'Пользователь успешно удален']);
            } else {
                return new Response(['success' => false, 'error' => 'Пользователь не найден'], 404);
            }
        } catch (Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при удалении пользователя'], 500);
        }
    }

    public function hello(): Response
    {
        return new Response(['success' => true, 'message' => 'Hello from UserController!']);
    }
}
