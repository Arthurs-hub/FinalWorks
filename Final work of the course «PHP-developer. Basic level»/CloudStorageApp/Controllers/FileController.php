<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\FileService;

class FileController extends BaseController
{
    private FileService $fileService;

    public function __construct()
    {
        $this->fileService = new FileService();
    }

    public function upload(Request $request): Response
    {
        $userId = $this->getCurrentUserId();
        $files = $_FILES; 
        $data = $request->getData();

        $result = $this->fileService->uploadFiles($userId, $files, $data);

        return new Response($result);
    }

    public function download(Request $request): Response
    {
        $fileId = $request->routeParams['id'] ?? null;
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->getFileForDownload($fileId, $userId);

        if (!$result['success']) {
            return new Response($result, $result['code'] ?? 404);
        }

        $this->sendFileResponse($result['file']);
        return new Response(['success' => true]);
    }

    public function remove(Request $request): Response
    {
        $fileId = $request->routeParams['id'] ?? null;
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

    public function getSharedFiles(Request $request): Response
    {
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->getSharedFilesList($userId);
        return new Response($result);
    }

    public function preview(Request $request): Response
    {
        $fileId = $request->routeParams['id'] ?? null;
        $userId = $this->getCurrentUserId();

        $result = $this->fileService->getFileForPreview($fileId, $userId);

        if (!$result['success']) {
            return new Response($result, $result['code'] ?? 404);
        }

        $this->sendFileResponse($result['file'], true);
        return new Response(['success' => true]);
    }

    public function getFileInfo(Request $request): Response
    {
        $fileId = $request->routeParams['id'] ?? null;
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

    private function sendFileResponse(array $file, bool $inline = false): void
    {
        $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];

        if (!file_exists($filePath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Физический файл не найден']);
            exit;
        }

        $disposition = $inline ? 'inline' : 'attachment';

        header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: ' . $disposition . '; filename="' . $file['filename'] . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        exit;
    }
}
