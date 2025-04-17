<?php

spl_autoload_register(function ($class) {
    
    $baseDir = __DIR__ . '/';
    
    
    $class = str_replace('Telegraph\\', '', $class);
    $file = $baseDir . str_replace('\\', '/', $class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});