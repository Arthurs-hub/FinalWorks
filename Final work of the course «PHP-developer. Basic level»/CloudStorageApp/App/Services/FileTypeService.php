<?php

namespace App\Services;

class FileTypeService
{
    public function isVideoFile(string $mimeType): bool
    {
        $videoMimeTypes = [
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/webm',
            'video/ogg',
            'video/3gpp',
            'video/x-flv',
            'video/x-matroska',
            'video/mp2t',
            'video/x-ms-asf',
        ];

        return in_array($mimeType, $videoMimeTypes);
    }

    public function isImageFile(string $mimeType): bool
    {
        $imageMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/svg+xml',
        ];

        return in_array($mimeType, $imageMimeTypes);
    }

    public function isPdfFile(string $mimeType): bool
    {
        return $mimeType === 'application/pdf';
    }

    public function isPreviewAvailable(string $mimeType): bool
    {
        return $this->isImageFile($mimeType) ||
            $this->isPdfFile($mimeType) ||
            $this->isVideoFile($mimeType);
    }
}
