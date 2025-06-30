<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\AuthMiddleware;
use App\Core\Logger;
use App\Services\UserService;
use App\Services\AdminService;
use Exception;

class AdminController
{
    private UserService $userService;
    private AdminService $adminService;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->adminService = new AdminService();
    }

    public function dashboard(): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $stats = $this->userService->getAdminStats();

            return new Response([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::dashboard error", [
                'error' => $e->getMessage(),
                'user_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при загрузке статистики'
            ], 500);
        }
    }

    public function getStats(Request $request): Response
    {
        $request;
        AuthMiddleware::requireAdmin();

        try {
            $stats = $this->userService->getAdminStats();

            return new Response([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::getStats error", [
                'error' => $e->getMessage(),
                'user_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при загрузке статистики'
            ], 500);
        }
    }

    public function getUsers(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $users = $this->userService->getAllUsersWithStats();

            return new Response([
                'success' => true,
                'users' => $users
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::getUsers error", [
                'error' => $e->getMessage(),
                'user_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при загрузке пользователей'
            ], 500);
        }
    }

    public function getUser(Request $request): Response
    {
        try {
            $userId = AuthMiddleware::getCurrentUserId();

            if (!$userId || !$this->userService->isAdmin($userId)) {
                return new Response(['success' => false, 'error' => 'Недостаточно прав'], 403);
            }

            $targetUserId = (int)($request->routeParams['id'] ?? 0);
            if (!$targetUserId) {
                return new Response(['success' => false, 'error' => 'ID пользователя не указан'], 400);
            }

            $user = $this->userService->getUserForAdmin($targetUserId);
            if (!$user) {
                return new Response(['success' => false, 'error' => 'Пользователь не найден'], 404);
            }

            return new Response([
                'success' => true,
                'user' => $user
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::getUser error", [
                'user_id' => $userId ?? null,
                'target_user_id' => $targetUserId ?? null,
                'error' => $e->getMessage()
            ]);
            return new Response(['success' => false, 'error' => 'Ошибка при получении данных пользователя'], 500);
        }
    }

    public function createUser(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $data = $request->getData();

            $errors = $this->userService->validateUserData($data);
            if (!empty($errors)) {
                return new Response([
                    'success' => false,
                    'error' => implode(', ', $errors)
                ], 400);
            }

            $userId = $this->userService->createUser($data);

            if ($userId) {
                Logger::info("User created by admin", [
                    'created_user_id' => $userId,
                    'admin_id' => AuthMiddleware::getCurrentUserId(),
                    'email' => $data['email']
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Пользователь успешно создан',
                    'user_id' => $userId
                ]);
            } else {
                return new Response([
                    'success' => false,
                    'error' => 'Ошибка при создании пользователя'
                ], 500);
            }
        } catch (Exception $e) {
            Logger::error("AdminController::createUser error", [
                'error' => $e->getMessage(),
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);
            return new Response([
                'success' => false,
                'error' => 'Ошибка при создании пользователя'
            ], 500);
        }
    }

    public function updateUser(Request $request): Response
    {
        try {
            AuthMiddleware::requireAdmin();

            $data = $request->getData();
            if (is_string($data)) {
                $data = json_decode($data, true);
            }

            if (!is_array($data)) {
                return new Response([
                    'success' => false,
                    'error' => 'Неверный формат данных'
                ], 400);
            }

            $id = $request->routeParams['id'] ?? null;
            if (!$id) {
                return new Response([
                    'success' => false,
                    'error' => 'ID пользователя не указан'
                ], 400);
            }

            $result = $this->adminService->updateUser($id, $data);

            if ($result) {
                return new Response(['success' => true]);
            } else {
                return new Response([
                    'success' => false,
                    'error' => 'Ошибка при обновлении пользователя'
                ], 400);
            }
        } catch (Exception $e) {
            return $this->handleError('updateUser', $e, 'Ошибка при обновлении пользователя');
        }
    }

    public function deleteUser(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $userId = $request->routeParams['id'] ?? null;
            if (!$userId) {
                return new Response([
                    'success' => false,
                    'error' => 'ID пользователя не указан'
                ], 400);
            }

            $currentUserId = AuthMiddleware::getCurrentUserId();

            if ($userId == $currentUserId) {
                return new Response([
                    'success' => false,
                    'error' => 'Нельзя удалить самого себя'
                ], 400);
            }

            $success = $this->userService->deleteUser($userId);

            if ($success) {
                Logger::info("User deleted by admin", [
                    'deleted_user_id' => $userId,
                    'admin_id' => $currentUserId
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Пользователь успешно удален'
                ]);
            } else {
                return new Response([
                    'success' => false,
                    'error' => 'Пользователь не найден'
                ], 404);
            }
        } catch (Exception $e) {
            Logger::error("AdminController::deleteUser error", [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при удалении пользователя'
            ], 500);
        }
    }

    public function banUser(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $userId = $request->routeParams['id'] ?? null;
            if (!$userId) {
                return new Response([
                    'success' => false,
                    'error' => 'ID пользователя не указан'
                ], 400);
            }

            $currentUserId = AuthMiddleware::getCurrentUserId();

            if ($userId == $currentUserId) {
                return new Response([
                    'success' => false,
                    'error' => 'Нельзя заблокировать самого себя'
                ], 400);
            }

            $success = $this->userService->banUser($userId);

            if ($success) {
                Logger::info("User banned by admin", [
                    'banned_user_id' => $userId,
                    'admin_id' => $currentUserId
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Пользователь заблокирован'
                ]);
            } else {
                return new Response([
                    'success' => false,
                    'error' => 'Пользователь не найден'
                ], 404);
            }
        } catch (Exception $e) {
            Logger::error("AdminController::banUser error", [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при блокировке пользователя'
            ], 500);
        }
    }

    public function unbanUser(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $userId = $request->routeParams['id'] ?? null;
            if (!$userId) {
                return new Response([
                    'success' => false,
                    'error' => 'ID пользователя не указан'
                ], 400);
            }

            $success = $this->userService->unbanUser($userId);

            if ($success) {
                Logger::info("User unbanned by admin", [
                    'unbanned_user_id' => $userId,
                    'admin_id' => AuthMiddleware::getCurrentUserId()
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Пользователь разблокирован'
                ]);
            } else {
                return new Response([
                    'success' => false,
                    'error' => 'Пользователь не найден'
                ], 404);
            }
        } catch (Exception $e) {
            Logger::error("AdminController::unbanUser error", [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при разблокировке пользователя'
            ], 500);
        }
    }

    public function makeAdmin(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $userId = $request->routeParams['id'] ?? null;
            if (!$userId) {
                return new Response([
                    'success' => false,
                    'error' => 'ID пользователя не указан'
                ], 400);
            }

            $success = $this->userService->makeAdmin($userId);

            if ($success) {
                Logger::info("User promoted to admin", [
                    'promoted_user_id' => $userId,
                    'admin_id' => AuthMiddleware::getCurrentUserId()
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Пользователь назначен администратором'
                ]);
            } else {
                return new Response([
                    'success' => false,
                    'error' => 'Пользователь не найден'
                ], 404);
            }
        } catch (Exception $e) {
            Logger::error("AdminController::makeAdmin error", [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при назначении администратора'
            ], 500);
        }
    }

    public function removeAdmin(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $userId = $request->routeParams['id'] ?? null;
            if (!$userId) {
                return new Response([
                    'success' => false,
                    'error' => 'ID пользователя не указан'
                ], 400);
            }

            $currentUserId = AuthMiddleware::getCurrentUserId();

            if ($userId == $currentUserId) {
                return new Response([
                    'success' => false,
                    'error' => 'Нельзя снять права администратора с самого себя'
                ], 400);
            }

            $success = $this->userService->removeAdmin($userId);

            if ($success) {
                Logger::info("Admin rights removed", [
                    'demoted_user_id' => $userId,
                    'admin_id' => $currentUserId
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Права администратора сняты'
                ]);
            } else {
                return new Response([
                    'success' => false,
                    'error' => 'Пользователь не найден'
                ], 404);
            }
        } catch (Exception $e) {
            Logger::error("AdminController::removeAdmin error", [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при снятии прав администратора'
            ], 500);
        }
    }

    public function getFiles(Request $request): Response
    {
        $request;
        AuthMiddleware::requireAdmin();

        try {
            $files = $this->adminService->getAllFiles();

            return new Response([
                'success' => true,
                'files' => $files
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::getFiles error", [
                'error' => $e->getMessage(),
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при загрузке файлов'
            ], 500);
        }
    }

    public function deleteFile(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $fileId = $request->routeParams['id'] ?? null;
            if (!$fileId) {
                return new Response([
                    'success' => false,
                    'error' => 'ID файла не указан'
                ], 400);
            }

            $success = $this->adminService->deleteFile($fileId);

            if ($success) {
                Logger::info("File deleted by admin", [
                    'file_id' => $fileId,
                    'admin_id' => AuthMiddleware::getCurrentUserId()
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Файл успешно удален'
                ]);
            } else {
                return new Response([
                    'success' => false,
                    'error' => 'Файл не найден'
                ], 404);
            }
        } catch (Exception $e) {
            Logger::error("AdminController::deleteFile error", [
                'error' => $e->getMessage(),
                'file_id' => $fileId ?? null,
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при удалении файла'
            ], 500);
        }
    }

    public function clearFiles(Request $request): Response
    {
        $request;
        AuthMiddleware::requireAdmin();

        try {
            $result = $this->adminService->cleanupOrphanedFiles();

            Logger::info("Files cleanup performed by admin", [
                'deleted_count' => $result['deleted_count'],
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => true,
                'message' => 'Очистка файлов завершена',
                'deleted_count' => $result['deleted_count']
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::clearFiles error", [
                'error' => $e->getMessage(),
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при очистке файлов: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getLogs(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $level = $request->getQueryParam('level', 'all');
            $limit = (int)$request->getQueryParam('limit', 100);

            $logs = Logger::getRecentLogs($limit, $level);

            return new Response([
                'success' => true,
                'logs' => $logs
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::getLogs error", [
                'error' => $e->getMessage(),
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при загрузке логов'
            ], 500);
        }
    }

    public function clearLogs(Request $request): Response
    {
        $request;
        AuthMiddleware::requireAdmin();

        try {
            $success = Logger::clearLogs();

            if ($success) {
                Logger::info("Logs cleared by admin", [
                    'admin_id' => AuthMiddleware::getCurrentUserId()
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Логи успешно очищены'
                ]);
            } else {
                return new Response([
                    'success' => false,
                    'error' => 'Ошибка при очистке логов'
                ], 500);
            }
        } catch (Exception $e) {
            Logger::error("AdminController::clearLogs error", [
                'error' => $e->getMessage(),
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при очистке логов'
            ], 500);
        }
    }

    public function getSystemHealth(Request $request): Response
    {
        $request;
        AuthMiddleware::requireAdmin();

        try {
            $health = $this->userService->getSystemHealth();

            return new Response([
                'success' => true,
                'health' => $health
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::getSystemHealth error", [
                'error' => $e->getMessage(),
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при проверке состояния системы'
            ], 500);
        }
    }

    public function getSecurityReport(Request $request): Response
    {
        $request;
        AuthMiddleware::requireAdmin();

        try {
            $report = $this->userService->getSecurityReport();

            return new Response([
                'success' => true,
                'report' => $report
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::getSecurityReport error", [
                'error' => $e->getMessage(),
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при генерации отчета безопасности'
            ], 500);
        }
    }

    public function exportUserData(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $userId = $request->routeParams['id'] ?? null;
            if (!$userId) {
                return new Response([
                    'success' => false,
                    'error' => 'ID пользователя не указан'
                ], 400);
            }

            $userData = $this->userService->exportUserData($userId);

            Logger::info("User data exported by admin", [
                'exported_user_id' => $userId,
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => true,
                'data' => $userData
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::exportUserData error", [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при экспорте данных пользователя'
            ], 500);
        }
    }

    public function bulkDeleteUsers(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $data = $request->getData();
            $userIds = $data['user_ids'] ?? [];

            if (empty($userIds) || !is_array($userIds)) {
                return new Response([
                    'success' => false,
                    'error' => 'Не указаны ID пользователей для удаления'
                ], 400);
            }

            $currentUserId = AuthMiddleware::getCurrentUserId();

            $userIds = array_filter($userIds, function ($id) use ($currentUserId) {
                return $id != $currentUserId;
            });

            $results = $this->userService->bulkDeleteUsers($userIds);

            Logger::info("Bulk user deletion performed by admin", [
                'total' => $results['total'],
                'success_count' => count($results['success']),
                'failed_count' => count($results['failed']),
                'admin_id' => $currentUserId
            ]);

            return new Response([
                'success' => true,
                'message' => 'Массовое удаление завершено',
                'results' => $results
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::bulkDeleteUsers error", [
                'error' => $e->getMessage(),
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при массовом удалении пользователей'
            ], 500);
        }
    }

    public function searchUsers(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $query = $request->getQueryParam('q', '');

            if (empty($query)) {
                return new Response([
                    'success' => false,
                    'error' => 'Поисковый запрос не указан'
                ], 400);
            }

            $users = $this->userService->searchUsers($query);

            return new Response([
                'success' => true,
                'users' => $users
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::searchUsers error", [
                'error' => $e->getMessage(),
                'query' => $query ?? '',
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при поиске пользователей'
            ], 500);
        }
    }

    public function getUserActivity(Request $request): Response
    {
        AuthMiddleware::requireAdmin();

        try {
            $userId = $request->routeParams['id'] ?? null;
            if (!$userId) {
                return new Response([
                    'success' => false,
                    'error' => 'ID пользователя не указан'
                ], 400);
            }

            $days = (int)$request->getQueryParam('days', 30);
            $activity = $this->userService->getUserActivity($userId, $days);

            return new Response([
                'success' => true,
                'activity' => $activity
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::getUserActivity error", [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при получении активности пользователя'
            ], 500);
        }
    }

    public function resetUserPassword(Request $request): Response
    {
        try {
            $currentUserId = AuthMiddleware::getCurrentUserId();

            if (!$this->userService->isAdmin($currentUserId)) {
                return new Response(['success' => false, 'error' => 'Недостаточно прав'], 403);
            }

            $targetUserId = (int)($request->routeParams['id'] ?? 0);

            if (!$targetUserId) {
                return new Response(['success' => false, 'error' => 'ID пользователя не указан'], 400);
            }

            $user = $this->userService->findUserById($targetUserId);
            if (!$user) {
                return new Response(['success' => false, 'error' => 'Пользователь не найден'], 404);
            }

            $tempPassword = $this->userService->resetPassword($user['email']);

            Logger::info("Password reset by admin", [
                'admin_id' => $currentUserId,
                'target_user_id' => $targetUserId,
                'target_email' => $user['email']
            ]);

            return new Response([
                'success' => true,
                'message' => 'Пароль успешно сброшен',
                'temp_password' => $tempPassword
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::resetUserPassword error", [
                'admin_id' => $currentUserId ?? null,
                'target_user_id' => $targetUserId ?? null,
                'error' => $e->getMessage()
            ]);
            return new Response(['success' => false, 'error' => 'Ошибка при сбросе пароля: ' . $e->getMessage()], 500);
        }
    }

    public function getActiveUsers(Request $request): Response
    {
        try {
            $currentUserId = AuthMiddleware::getCurrentUserId();

            if (!$this->userService->isAdmin($currentUserId)) {
                return new Response(['success' => false, 'error' => 'Недостаточно прав'], 403);
            }

            $days = (int)($request->getData()['days'] ?? 30);
            $days = max(1, min(365, $days)); // Ограничиваем от 1 до 365 дней

            $activeUsers = $this->userService->getActiveUsers($days);

            return new Response([
                'success' => true,
                'active_users' => $activeUsers,
                'days' => $days,
                'count' => count($activeUsers)
            ]);
        } catch (Exception $e) {
            Logger::error("AdminController::getActiveUsers error", [
                'admin_id' => $currentUserId ?? null,
                'days' => $days ?? null,
                'error' => $e->getMessage()
            ]);
            return new Response(['success' => false, 'error' => 'Ошибка при получении активных пользователей'], 500);
        }
    }

    public function exportUsers(Request $request): Response
    {
        try {
            AuthMiddleware::requireAdmin();

            $exportResult = $this->adminService->exportUsersToCSV();

            if (!$exportResult['success']) {
                return new Response($exportResult, 500);
            }

            return new Response([
                'success' => true,
                'file_download' => true,
                'file_path' => $exportResult['file_path'],
                'filename' => $exportResult['filename'],
                'content_type' => 'text/csv; charset=utf-8'
            ]);
        } catch (Exception $e) {
            return $this->handleError('exportUsers', $e, 'Ошибка при экспорте пользователей');
        }
    }

    private function handleError(string $method, Exception $e, string $userMessage): Response
    {
        Logger::error("AdminController::{$method} error", [
            'error' => $e->getMessage(),
            'admin_id' => AuthMiddleware::getCurrentUserId()
        ]);

        return new Response([
            'success' => false,
            'error' => $userMessage
        ], 500);
    }
}
