<?php

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Services\UserService;
use Exception;
use PDOException;
use RuntimeException;
use PDO;

class UserController
{
    private UserService $userService;
    private Db $db;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->db = new Db();
    }

    public function register(Request $request): Response
    {
        $data = $request->getData();

        if (empty($data['email']) || empty($data['password']) || empty($data['first_name']) || empty($data['last_name'])) {
            return new Response(['success' => false, 'error' => 'Не все обязательные поля заполнены']);
        }

        $conn = null;
        try {
            $conn = $this->db->getConnection();

            if ($this->userService->findUserByEmail($data['email'])) {
                return new Response(['success' => false, 'error' => 'Пользователь с таким email уже существует']);
            }

            $conn->beginTransaction();

            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

            $newUserId = $this->userService->createUser($data);

            if (!$newUserId) {
                throw new RuntimeException('Ошибка создания пользователя');
            }

            $stmt = $conn->prepare("
            INSERT INTO directories (name, parent_id, user_id) 
            VALUES ('Корневая папка', NULL, ?)
        ");

            if (!$stmt->execute([$newUserId])) {
                throw new RuntimeException('Ошибка создания корневой директории');
            }

            $conn->commit();
            return new Response(['success' => true, 'message' => 'Регистрация успешна!']);
        } catch (RuntimeException $e) {
            if ($conn && $conn->inTransaction()) {
                $conn->rollBack();
            }
            return new Response(['success' => false, 'error' => $e->getMessage()]);
        } catch (PDOException $e) {
            if ($conn && $conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("Database error during registration: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка базы данных при регистрации']);
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

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT id, email, password, role, first_name, last_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log("Login attempt for: " . $email);
            error_log("User found: " . ($user ? 'yes' : 'no'));
            if ($user) {
                error_log("User role: " . $user['role']);
            }

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];


                return new Response([
                    'success' => true,
                    'role' => $user['role'],
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'role' => $user['role']
                    ]
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

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {

                $_SESSION['role'] = $user['role'];

                return new Response([
                    'success' => true,
                    'user' => $user
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

            unset($user['password']);
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
            foreach ($users as &$user) {
                unset($user['password']);
            }
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

            if ($userId != $_SESSION['user_id'] && !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                return new Response(['success' => false, 'error' => 'Недостаточно прав'], 403);
            }

            if (isset($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $success = $this->userService->updateUser($userId, $data);
            if (!$success) {
                return new Response(['success' => false, 'error' => 'Ошибка при обновлении пользователя']);
            }

            return new Response(['success' => true, 'message' => 'Пользователь успешно обновлен']);
        } catch (Exception $e) {
            return new Response(['success' => false, 'error' => 'Ошибка при обновлении пользователя'], 500);
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
                return new Response(['success' => false, 'error' => 'Необходимо указать email']);
            }

            $user = $this->userService->findUserByEmail($data['email']);

            if (!$user) {
                return new Response(['success' => true, 'message' => 'Если пользователь существует, инструкции отправлены']);
            }

            $tempPassword = $this->generateTempPassword();
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

            $success = $this->userService->updateUser($user['id'], ['password' => $hashedPassword]);

            if (!$success) {
                return new Response(['success' => false, 'error' => 'Ошибка при сбросе пароля']);
            }

            return new Response([
                'success' => true,
                'message' => 'Пароль сброшен',
                'temp_password' => $tempPassword
            ]);
        } catch (Exception $e) {
            return new Response(['success' => false, 'error' => 'Ошибка при сбросе пароля: ' . $e->getMessage()], 500);
        }
    }

    private function generateTempPassword(): string
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    }
}
