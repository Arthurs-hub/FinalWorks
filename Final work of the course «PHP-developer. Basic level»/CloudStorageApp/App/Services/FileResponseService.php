<?php

namespace App\Services;

class FileResponseService
{
    public function sendFileResponse(array $file, bool $inline = false): void
    {
        $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];

        if (!file_exists($filePath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Физический файл не найден']);
            exit;
        }

        $size = filesize($filePath);
        $mimeType = $file['mime_type'] ?: 'application/octet-stream';
        $disposition = $inline ? 'inline' : 'attachment';

        $start = 0;
        $end = $size - 1;
        $length = $size;

        header("Content-Type: $mimeType");
        header("Accept-Ranges: bytes");

        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = $_SERVER['HTTP_RANGE'];
            if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
                $start = $matches[1] !== '' ? intval($matches[1]) : 0;
                $end = $matches[2] !== '' ? intval($matches[2]) : $end;

                if ($start > $end || $end >= $size) {
                    header("HTTP/1.1 416 Requested Range Not Satisfiable");
                    header("Content-Range: bytes */$size");
                    exit;
                }

                $length = $end - $start + 1;
                header('HTTP/1.1 206 Partial Content');
                header("Content-Range: bytes $start-$end/$size");
            }
        } else {
            header('HTTP/1.1 200 OK');
        }

        header("Content-Length: $length");
        header("Content-Disposition: $disposition; filename=\"" . basename($file['filename']) . "\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen($filePath, 'rb');
        if ($fp === false) {
            http_response_code(500);
            exit;
        }

        fseek($fp, $start);

        $bufferSize = 8192;
        $bytesSent = 0;

        while (!feof($fp) && $bytesSent < $length) {
            $readLength = min($bufferSize, $length - $bytesSent);
            $buffer = fread($fp, $readLength);
            echo $buffer;
            flush();
            $bytesSent += $readLength;
        }

        fclose($fp);
        exit;
    }
}
