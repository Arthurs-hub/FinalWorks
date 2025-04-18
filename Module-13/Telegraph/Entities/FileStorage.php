<?php

namespace Telegraph\Entities;

class FileStorage extends Storage
{
    private $directory;
    public function __construct($directory)
    {
        $this->directory = $directory;
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
    }
    public function create($object)
    {
        $slug = $object->getSlug();
        $filePath = $this->directory . DIRECTORY_SEPARATOR . $slug . '.txt';
        $data = serialize($object);
        if (file_put_contents($filePath, $data) === false) {
            throw new \RuntimeException("Unable to create file: {$filePath}");
        }
        return $slug;
    }
    public function read($slug)
    {
        $filePath = $this->directory . DIRECTORY_SEPARATOR . $slug . '.txt';
        if (!file_exists($filePath)) {
            return null;
        }
        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new \RuntimeException("Unable to read file: {$filePath}");
        }
        return unserialize($data);
    }
    public function update($slug, $updatedObject)
    {
        $filePath = $this->directory . DIRECTORY_SEPARATOR . $slug . '.txt';
        if (!file_exists($filePath)) {
            return false;
        }
        $data = serialize($updatedObject);
        if (file_put_contents($filePath, $data) === false) {
            throw new \RuntimeException("Unable to update file: {$filePath}");
        }
        return true;
    }
    public function delete($slug)
    {
        $filePath = $this->directory . DIRECTORY_SEPARATOR . $slug . '.txt';
        if (!file_exists($filePath)) {
            return false;
        }
        if (!unlink($filePath)) {
            throw new \RuntimeException("Unable to delete file: {$filePath}");
        }
        return true;
    }
    public function list()
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.txt');
        $slugs = [];
        foreach ($files as $file) {
            $slugs[] = basename($file, '.txt');
        }
        return $slugs;
    }
}
