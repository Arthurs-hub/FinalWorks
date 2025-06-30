<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\FileService;
use Exception;


class FileController
{
    private FileService $fileService;


    public function __construct()
    {
        $this->fileService = new FileService();
    }

    public function upload(Request $request): Response
    {
        $request;
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];

            if (!isset($_FILES['files'])) {
                return new Response(['success' => false, 'error' => 'Файлы не выбраны'], 400);
            }

            $files = $_FILES['files'];
            $directoryId = $_POST['directory_id'] ?? 'root';
            $paths = json_decode($_POST['paths'] ?? '[]', true);

            $responses = [];
            for ($i = 0; $i < count($files['name']); $i++) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $responses[] = [
                        'file' => $file['name'],
                        'success' => false,
                        'error' => 'Ошибка загрузки файла'
                    ];
                    continue;
                }

                $relativePath = $paths[$i] ?? $file['name'];

                error_log("Uploading file: {$file['name']} with relative path: $relativePath");

                $response = $this->fileService->upload($file, $directoryId, $userId, $relativePath);
                $data = $response->getData();

                $responses[] = [
                    'file' => $file['name'],
                    'success' => $data['success'] ?? false,
                    'message' => $data['message'] ?? null,
                    'error' => $data['error'] ?? null,
                ];
            }

            return new Response([
                'success' => true,
                'files' => $responses
            ]);
        } catch (Exception $e) {
            error_log("FileController::upload error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при загрузке файлов'], 500);
        }
    }

    public function list(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];
            $data = $request->getData();
            $directoryId = $data['directory_id'] ?? 'root';

            $result = $this->fileService->getFilesList($userId, $directoryId);

            return new Response([
                'success' => true,
                'files' => $result['files'],
                'directories' => $result['directories'],
                'current_directory' => $result['current_directory'],
                'current_directory_id' => $result['current_directory_id']
            ]);
        } catch (Exception $e) {
            error_log("FileController::list error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при получении списка файлов'], 500);
        }
    }

    public function rename(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];
            $data = $request->getData();

            if (!isset($data['file_id']) || !isset($data['new_name'])) {
                return new Response([
                    'success' => false,
                    'error' => 'Не указан ID файла или новое имя'
                ], 400);
            }

            $fileId = (int)$data['file_id'];
            $newName = trim($data['new_name']);

            if (empty($newName)) {
                return new Response([
                    'success' => false,
                    'error' => 'Новое имя файла не может быть пустым'
                ], 400);
            }

            return $this->fileService->rename($fileId, $newName, $userId);
        } catch (Exception $e) {
            error_log("FileController::rename error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при переименовании файла'], 500);
        }
    }

    public function share(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];
            $data = $request->getData();

            if (!isset($data['file_id']) || !isset($data['email'])) {
                return new Response([
                    'success' => false,
                    'error' => 'Не указан ID файла или email'
                ], 400);
            }

            $fileId = (int)$data['file_id'];
            $targetEmail = trim($data['email']);

            return $this->fileService->share($fileId, $targetEmail, $userId);
        } catch (Exception $e) {
            error_log("FileController::share error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при предоставлении доступа'], 500);
        }
    }

    public function getFileInfo(Request $request): Response
    {
        return $this->get($request);
    }

    public function download(Request $request): void
    {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Пользователь не авторизован']);
            exit;
        }

        try {
            $userId = $_SESSION['user_id'];
            $fileId = (int)($request->routeParams['id'] ?? 0);

            if (!$fileId) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'ID файла не указан']);
                exit;
            }

            $this->fileService->downloadFile($fileId, $userId);
        } catch (Exception $e) {
            error_log("FileController::download error: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Ошибка при скачивании файла']);
            exit;
        }
    }

    public function unshare(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];
            $data = $request->getData();

            if (!isset($data['file_id'])) {
                return new Response([
                    'success' => false,
                    'error' => 'Не указан ID файла'
                ], 400);
            }

            $fileId = (int)$data['file_id'];

            return $this->fileService->unshare($fileId, $userId);
        } catch (Exception $e) {
            error_log("FileController::unshare error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при отзыве доступа'], 500);
        }
    }

    public function move(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];
            $data = $request->getData();

            if (!isset($data['file_id']) || !isset($data['target_directory_id'])) {
                return new Response([
                    'success' => false,
                    'error' => 'Не указан ID файла или целевая папка'
                ], 400);
            }

            $fileId = (int)$data['file_id'];
            $targetDirId = $data['target_directory_id'];

            return $this->fileService->move($fileId, $targetDirId, $userId);
        } catch (Exception $e) {
            error_log("FileController::move error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при перемещении файла'], 500);
        }
    }

    public function remove(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];
            $fileId = (int)($request->routeParams['id'] ?? 0);

            if (!$fileId) {
                return new Response(['success' => false, 'error' => 'ID файла не указан'], 400);
            }

            $this->fileService->deleteFile($fileId, $userId);

            return new Response([
                'success' => true,
                'message' => 'Файл успешно удален'
            ]);
        } catch (Exception $e) {
            error_log("FileController::remove error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при удалении файла'], 500);
        }
    }

    public function get(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];
            $fileId = (int)($request->routeParams['id'] ?? 0);

            if (!$fileId) {
                return new Response(['success' => false, 'error' => 'ID файла не указан'], 400);
            }

            $fileInfo = $this->fileService->getFileInfo($fileId, $userId);

            if (!$fileInfo) {
                return new Response(['success' => false, 'error' => 'Файл не найден'], 404);
            }

            return new Response([
                'success' => true,
                'file' => $fileInfo
            ]);
        } catch (Exception $e) {
            error_log("FileController::get error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при получении информации о файле'], 500);
        }
    }

    public function add(Request $request): Response
    {
        return $this->upload($request);
    }

    public function preview(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $fileId = $_GET['id'] ?? null;
            if (!$fileId) {
                return new Response(['success' => false, 'error' => 'ID файла не указан'], 400);
            }

            $_GET['inline'] = '1';
            $this->download($request);

            return new Response(['success' => false, 'error' => 'Unexpected error'], 500);
        } catch (Exception $e) {
            error_log("FileController::preview error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при предварительном просмотре'], 500);
        }
    }
}
