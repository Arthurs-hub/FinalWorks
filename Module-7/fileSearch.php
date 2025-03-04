<?php

declare(strict_types=1);

$searchRoot = 'search_folder'; 
$searchName = 'test.txt'; 
$searchResult = []; 

function searchFiles(string $searchRoot, string $searchName, array &$searchResult): void {
    $items = scandir($searchRoot);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $searchRoot . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            searchFiles($path, $searchName, $searchResult);
        } elseif (is_file($path) && $item === $searchName) {
            $searchResult[] = $path;
        }
    }
}

searchFiles($searchRoot, $searchName, $searchResult);

if (empty($searchResult)) {
    echo "Поиск не дал результатов.\n";
} else {
    foreach ($searchResult as $file) {
        echo $file . "\n";
    }
}
$nonZeroSizeFiles = array_filter($searchResult, function ($file) {
    return filesize($file) > 0;
});

    if (!empty($nonZeroSizeFiles)) {
        echo "Файлы с ненулевым размером:\n";
        foreach ($nonZeroSizeFiles as $file) {
            echo $file . "\n";
        }
    }
