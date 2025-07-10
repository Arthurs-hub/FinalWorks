<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AdminService;

class AdminController extends BaseController
{
    private AdminService $adminService;

    public function __construct()
    {
        $this->adminService = new AdminService();
    }

    public function dashboard(): Response
    {
        return $this->executeWithAuth(function () {
            return $this->handleServiceResult($this->adminService->getDashboard());
        }, true, true);
    }

    public function getStats(): Response
    {
        return $this->executeWithAuth(function () {
            return $this->handleServiceResult($this->adminService->getStats());
        }, true, true);
    }

    public function getUsers(Request $request): Response
    {
        return $this->executeWithAuth(function () {
            return $this->handleServiceResult($this->adminService->getUsers());
        }, true, true);
    }

    public function getUser(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $userId = (int)($request->routeParams['id'] ?? 0);
            return $this->handleServiceResult($this->adminService->getUser($userId));
        }, true, true);
    }

    public function createUser(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            return $this->handleServiceResult($this->adminService->createUser($request->getData()));
        }, true, true);
    }

    public function updateUser(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $userId = $request->routeParams['id'] ?? null;
            $data = $request->getData();
            return $this->handleServiceResult($this->adminService->updateUser($userId, $data));
        }, true, true);
    }

    public function deleteUser(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $userId = $request->routeParams['id'] ?? null;
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->deleteUser($userId, $currentUserId));
        }, true, true);
    }

    public function banUser(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $userId = $request->routeParams['id'] ?? null;
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->banUser($userId, $currentUserId));
        }, true, true);
    }

    public function unbanUser(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $userId = $request->routeParams['id'] ?? null;
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->unbanUser($userId, $currentUserId));
        }, true, true);
    }

    public function makeAdmin(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $userId = $request->routeParams['id'] ?? null;
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->makeAdmin($userId, $currentUserId));
        }, true, true);
    }

    public function removeAdmin(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $userId = $request->routeParams['id'] ?? null;
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->removeAdmin($userId, $currentUserId));
        }, true, true);
    }

    public function getFiles(Request $request): Response
    {
        return $this->executeWithAuth(function () {
            return $this->handleServiceResult($this->adminService->getFiles());
        }, true, true);
    }

    public function deleteFile(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $fileId = $request->routeParams['id'] ?? null;
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->deleteFile($fileId, $currentUserId));
        }, true, true);
    }

    public function clearFiles(Request $request): Response
    {
        return $this->executeWithAuth(function () {
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->clearFiles($currentUserId));
        }, true, true);
    }

    public function getLogs(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $level = $request->getQueryParam('level', 'all');
            $limit = (int)$request->getQueryParam('limit', 100);
            return $this->handleServiceResult($this->adminService->getLogs($level, $limit));
        }, true, true);
    }

    public function clearLogs(Request $request): Response
    {
        return $this->executeWithAuth(function () {
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->clearLogs($currentUserId));
        }, true, true);
    }

    public function getSystemHealth(Request $request): Response
    {
        error_log('getSystemHealth called, session user_id: ' . ($_SESSION['user_id'] ?? 'none'));
        error_log('Session is_admin: ' . ($_SESSION['is_admin'] ?? 'not set'));

        return $this->executeWithAuth(function () {
            $result = $this->adminService->getSystemHealth();
            error_log('getSystemHealth result: ' . json_encode($result));
            return $this->handleServiceResult($result);
        }, true, true);
    }

    public function getSecurityReport(Request $request): Response
    {
        return $this->executeWithAuth(function () {
            return $this->handleServiceResult($this->adminService->getSecurityReport());
        }, true, true);
    }

    public function exportUserData(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $userId = $request->routeParams['id'] ?? null;
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->exportUserData($userId, $currentUserId));
        }, true, true);
    }

    public function bulkDeleteUsers(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $data = $request->getData();
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->bulkDeleteUsers($data, $currentUserId));
        }, true, true);
    }

    public function searchUsers(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $query = $request->getQueryParam('q', '');
            return $this->handleServiceResult($this->adminService->searchUsers($query));
        }, true, true);
    }

    public function getUserActivity(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $userId = $request->routeParams['id'] ?? null;
            $days = (int)$request->getQueryParam('days', 30);
            return $this->handleServiceResult($this->adminService->getUserActivity($userId, $days));
        }, true, true);
    }

    public function resetUserPassword(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $targetUserId = (int)($request->routeParams['id'] ?? 0);
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->adminService->resetUserPassword($targetUserId, $currentUserId));
        }, true, true);
    }

    public function getActiveUsers(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $days = (int)($request->getData()['days'] ?? 30);
            return $this->handleServiceResult($this->adminService->getActiveUsers($days));
        }, true, true);
    }

    public function exportUsers(Request $request): Response
    {
        return $this->executeWithAuth(function () {
            $fileInfo = $this->adminService->exportUsersToCSV();

            if (empty($fileInfo['success']) || !$fileInfo['success']) {
                return new Response(['success' => false, 'error' => $fileInfo['error'] ?? 'Ошибка при экспорте'], 500);
            }

            $filePath = $fileInfo['file_path'] ?? null;
            $filename = $fileInfo['filename'] ?? 'users_export.csv';

            if (!$filePath || !file_exists($filePath)) {
                return new Response(['success' => false, 'error' => 'Файл не найден'], 404);
            }

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($filePath);
            unlink($filePath);
            exit;
        }, true, true);
    }
}
