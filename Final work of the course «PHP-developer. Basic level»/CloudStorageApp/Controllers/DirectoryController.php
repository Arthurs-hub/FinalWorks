<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\DirectoryService;


class DirectoryController extends BaseController
{
    private DirectoryService $directoryService;

    public function __construct()
    {
        $this->directoryService = new DirectoryService();
    }

    public function add(Request $request): Response
    {
        $userId = $this->getCurrentUserId();
        $data = $request->getData();
        $result = $this->directoryService->addDirectory($data, $userId);
        return new Response($result);
    }

    public function rename(Request $request): Response
    {
        $userId = $this->getCurrentUserId();
        $data = $request->getData();
        $result = $this->directoryService->renameDirectory($data, $userId);
        return new Response($result);
    }

    public function get(Request $request): Response
    {
        $directoryId = $request->routeParams['id'] ?? null;
        $userId = $this->getCurrentUserId();

        $result = $this->directoryService->getDirectory($directoryId, $userId);

        if ($result === null) {
            $result = ['success' => false, 'error' => 'Папка не найдена'];
        }

        return new Response($result);
    }

    public function move(Request $request): Response
    {
        $userId = $this->getCurrentUserId();
        $data = $request->getData();
        $result = $this->directoryService->moveDirectory($data, $userId);
        return new Response($result);
    }

    public function download(Request $request): Response
    {
        $directoryId = $request->routeParams['id'] ?? null;
        $userId = $this->getCurrentUserId();
        return $this->directoryService->downloadDirectory($directoryId, $userId);
    }

    public function delete(Request $request): Response
    {
        $directoryId = $request->routeParams['id'] ?? null;
        $userId = $this->getCurrentUserId();

        $isAdmin = $this->isCurrentUserAdmin();

        $result = $this->directoryService->deleteDirectory($directoryId, $userId, $isAdmin);
        return new Response($result);
    }

    public function share(Request $request): Response
    {
        $userId = $this->getCurrentUserId();
        $data = $request->getData();
        $result = $this->directoryService->shareDirectory($data, $userId);
        return new Response($result);
    }

    public function unshare(Request $request): Response
    {
        $userId = $this->getCurrentUserId();
        $data = $request->getData();
        $result = $this->directoryService->unshareDirectory($data, $userId);
        return new Response($result);
    }

    public function getSharedDirectories(Request $request): Response
    {
        $userId = $this->getCurrentUserId();
        $result = $this->directoryService->getSharedDirectoriesList($userId);
        return new Response($result);
    }

    public function list(Request $request): Response
    {
        $directoryId = $request->getQueryParam('directory_id', 'root');
        $userId = $this->getCurrentUserId();
        $result = $this->directoryService->getDirectory($directoryId, $userId);
        return new Response($result);
    }

    protected function isCurrentUserAdmin(): bool
    {
        return !empty($_SESSION['is_admin']);
    }
}
