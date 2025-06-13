<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\UserService;
use Exception;

class AdminController
{
    private UserService $service;

    public function __construct()
    {
        $this->service = new UserService();
    }

    public function list(): Response
    {
        if (!$this->isAdmin()) {
            return new Response(['success' => false, 'error' => 'Доступ запрещён'], 403);
        }

        try {
            $users = $this->service->getAllUsers();
            return new Response(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            error_log("AdminController::list exception: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при загрузке пользователей'], 500);
        }
    }

    private function isAdmin(): bool
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public function get(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return new Response(['success' => false, 'error' => 'Доступ запрещён'], 403);
        }

        try {
            $id = $request->routeParams['id'] ?? null;

            if (!$id) {
                return new Response(['success' => false, 'error' => 'ID пользователя не указан'], 400);
            }

            $user = $this->service->getUserById($id);

            if (!$user) {
                return new Response(['success' => false, 'error' => 'Пользователь не найден'], 404);
            }

            unset($user['password']);
            return new Response(['success' => true, 'user' => $user]);
        } catch (Exception $e) {
            error_log("AdminController::get exception: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при получении данных пользователя'], 500);
        }
    }

    public function delete(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return new Response(['success' => false, 'error' => 'Доступ запрещён'], 403);
        }

        try {
            $id = $request->routeParams['id'] ?? null;

            if (!$id) {
                return new Response(['success' => false, 'error' => 'ID пользователя не указан'], 400);
            }

            if ($id == $_SESSION['user_id']) {
                return new Response(['success' => false, 'error' => 'Нельзя удалить собственный аккаунт'], 400);
            }

            $result = $this->service->deleteUser($id);

            if ($result) {
                return new Response(['success' => true, 'message' => 'Пользователь успешно удален']);
            } else {
                return new Response(['success' => false, 'error' => 'Ошибка при удалении пользователя'], 500);
            }
        } catch (Exception $e) {
            error_log("AdminController::delete exception: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при удалении пользователя'], 500);
        }
    }

    public function update(Request $request): Response
    {
        if (!$this->isAdmin()) {
            return new Response(['success' => false, 'error' => 'Доступ запрещён'], 403);
        }

        try {
            $id = $request->routeParams['id'] ?? null;
            $data = $request->getData();

            if (!$id) {
                return new Response(['success' => false, 'error' => 'ID пользователя не указан'], 400);
            }

            $existingUser = $this->service->getUserById($id);
            if (!$existingUser) {
                return new Response(['success' => false, 'error' => 'Пользователь не найден'], 404);
            }

            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            } else {
                unset($data['password']);
            }

            $result = $this->service->updateUser($id, $data);

            if ($result) {
                return new Response(['success' => true, 'message' => 'Данные пользователя успешно обновлены']);
            } else {
                return new Response(['success' => false, 'error' => 'Ошибка при обновлении данных пользователя'], 500);
            }
        } catch (Exception $e) {
            error_log("AdminController::update exception: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при обновлении данных пользователя'], 500);
        }
    }

    public function create(Request $request): Response
    {
        error_log("AdminController::create called");

        if (!$this->isAdmin()) {
            error_log("AdminController::create - not admin");
            return new Response(['success' => false, 'error' => 'Доступ запрещён'], 403);
        }

        try {
            $data = $request->getData();
            error_log("AdminController::create - received data: " . json_encode($data));

            if (empty($data['email']) || empty($data['first_name']) || empty($data['last_name']) || empty($data['password'])) {
                error_log("AdminController::create - missing required fields");
                return new Response(['success' => false, 'error' => 'Заполните все обязательные поля'], 400);
            }

            $existingUser = $this->service->findUserByEmail($data['email']);
            if ($existingUser) {
                error_log("AdminController::create - user already exists");
                return new Response(['success' => false, 'error' => 'Пользователь с таким email уже существует'], 400);
            }

            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

            if (!isset($data['role'])) {
                $data['role'] = 'user';
            }

            error_log("AdminController::create - calling service->createUser");
            $result = $this->service->createUser($data);

            if ($result) {
                error_log("AdminController::create - success");
                return new Response(['success' => true, 'message' => 'Пользователь успешно создан']);
            } else {
                error_log("AdminController::create - service returned false");
                return new Response(['success' => false, 'error' => 'Ошибка при создании пользователя'], 500);
            }
        } catch (Exception $e) {
            error_log("AdminController::create exception: " . $e->getMessage());
            error_log("AdminController::create stack trace: " . $e->getTraceAsString());
            return new Response(['success' => false, 'error' => 'Ошибка при создании пользователя: ' . $e->getMessage()], 500);
        }
    }
}
