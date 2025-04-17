<?php

namespace Telegraph\Entities;
class FileStorage 
{
    private $directory;

    public function __construct($directory)
    {
        $this->directory = $directory;
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}