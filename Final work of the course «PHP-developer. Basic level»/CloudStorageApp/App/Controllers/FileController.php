<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\IFileService;
use App\Services\FileResponseService;
use App\Services\FileTypeService;

class FileController extends BaseController
{
    private IFileService $fileService;
    private FileResponseService $fileResponseService;
    private FileTypeService $fileTypeService;

    public function __construct(IFileService $fileService, FileResponseService $fileResponseService, FileTypeService $fileTypeService)
    {
        $this->fileService = $fileService;
        $this->fileResponseService = $fileResponseService;
        $this->fileTypeService = $fileTypeService;
    }

    public function upload(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $userId = $this->getCurrentUserId();
            $files = $_FILES ?? [];
            $postData = $_POST ?? [];

            $result = $this->fileService->uploadFiles($userId, $files, $postData);
            return $this->handleServiceResult($result);
        });
    }

    public function add(Request $request): Response
    {
        return $this->upload($request);
    }

    public function download(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $fileId = (int) ($request->routeParams['id'] ?? 0);
            $result = $this->fileService->downloadFile($fileId, $this->getCurrentUserId());

            if ($result['success']) {
                $this->fileResponseService->sendFileResponse($result['file']);
            }

            return new Response($result, $result['code'] ?? 404);
        });
    }

    public function remove(Request $request): Response
    {
        $fileId = (int) ($request->routeParams['id'] ?? 0);
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->deleteFile($fileId, $userId);
        return new Response($result);
    }

    public function list(Request $request): Response
    {
        $directoryId = $request->getQueryParam('directory_id', 'root');
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->listFiles($directoryId, $userId);
        return new Response($result);
    }

    public function rename(Request $request): Response
    {
        $data = $request->getData();
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->renameFile($data['file_id'] ?? null, $data, $userId);
        return new Response($result);
    }

    public function move(Request $request): Response
    {
        $data = $request->getData();
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->moveFile($data['file_id'] ?? null, $data, $userId);
        return new Response($result);
    }

    public function share(Request $request): Response
    {
        $data = $request->getData();
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->shareFile($data['file_id'] ?? null, $data, $userId);
        return new Response($result);
    }

    public function shareWithUser(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $fileId = (int) ($request->routeParams['id'] ?? 0);
            $targetUserId = (int) ($request->routeParams['user_id'] ?? 0);
            $userId = $this->getCurrentUserId();

            $data = ['user_id' => $targetUserId];

            $result = $this->fileService->shareFile($fileId, $data, $userId);
            return $this->handleServiceResult($result);
        });
    }

    public function unshareFromUser(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $fileId = (int) ($request->routeParams['id'] ?? 0);
            $targetUserId = (int) ($request->routeParams['user_id'] ?? 0);
            $userId = $this->getCurrentUserId();

            $result = $this->fileService->unshareFile($fileId, $targetUserId);
            return $this->handleServiceResult($result);
        });
    }

    public function getSharedFiles(Request $request): Response
    {
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->getSharedFilesList($userId);
        return new Response($result);
    }

    public function preview(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $fileId = (int) ($request->routeParams['id'] ?? 0);
            $result = $this->fileService->previewFile($fileId, $this->getCurrentUserId());

            if ($result['success']) {
                $this->fileResponseService->sendFileResponse($result['file'], true);
            }

            return new Response($result, $result['code'] ?? 404);
        });
    }

    public function getFileInfo(Request $request): Response
    {
        $fileId = isset($request->routeParams['id']) ? (int) $request->routeParams['id'] : null;
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->getFileInformation($fileId, $userId);
        return new Response($result);
    }

    public function bulkDelete(Request $request): Response
    {
        $data = $request->getData();
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->bulkDeleteFiles($data, $userId);
        return new Response($result);
    }

    public function searchFiles(Request $request): Response
    {
        $query = $request->getQueryParam('q', '');
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->searchUserFiles($query, $userId);
        return new Response($result);
    }

    public function unshare(Request $request): Response
    {
        $data = $request->getData();
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->unshareFile($data['file_id'] ?? null, $userId);
        return new Response($result);
    }

    public function get(Request $request): Response
    {
        return $this->getFileInfo($request);
    }

    public function getShares(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $fileId = (int) ($request->routeParams['id'] ?? 0);
            $userId = $this->getCurrentUserId();

            if (!$fileId) {
                return new Response(['success' => false, 'error' => 'ID файла не указан'], 400);
            }

            $result = $this->fileService->getFileShares($fileId, $userId);
            return new Response($result);
        });
    }
}
