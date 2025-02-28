<?php

$textStorage = [
    0 => [
        'title' => '',
        'text' => ''
    ],
    1 => [
        'title' => '',
        'text' => ''
    ],

];

function add(string $title, string $text, array &$storage, ?int $index = null): void
{
    if ($index !== null) {
        $storage[$index] = [
            'title' => $title,
            'text' => $text,
        ];
    } else {
        $storage[] = [
            'title' => $title,
            'text' => $text,
        ];
    }
}

add('Добавленный Заголовок 1', 'Добавленный Текст 1', $textStorage, 0);
add('Добавленный Заголовок 2', 'Добавленный Текст 2', $textStorage, 1);


echo "После добавления текстов:\n";
print_r($textStorage);

$result = remove(0, $textStorage);
$result1 = remove(5, $textStorage);
function remove(int $index, array &$storage): bool
{
    global $textStorage;
    if (array_key_exists($index, $storage)) {
        unset($storage[$index]);
        $storage = array_values($storage);
        return true;
    }

    return false;
}

var_dump($result, $result1);

print_r($textStorage);

function edit(int $index, string $newTitle, string $newText, array &$storage): bool
{
    if (array_key_exists($index, $storage)) {
        $storage[$index] = [
            'title' => $newTitle,
            'text' => $newText,
        ];
        return true;
    }

    return false;
}


$result2 = edit(0, 'Новый Заголовок', 'Новый Текст', $textStorage);

print_r($textStorage);

$result3 = edit(5, 'Обновленный Заголовок', 'Обновленный Текст', $textStorage);

var_dump($result3);