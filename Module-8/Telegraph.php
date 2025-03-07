<?php

class TelegraphText {
    public string $title;
    public string $text;
    public string $author;
    public string $published;
    public string $slug ='test_text_file';
    const FILE_EXTENSION = '.txt';
    
    public function __construct(string $title, string $author, string $text) {
        $this->title = $title;
        $this->author = $author;
        $this->text = $text;
        $this->published = date('Y-m-d H:i:s');
        $this->slug = str_replace(' ', '-', $title);
    }
    public function storeText(): string {
        $data = [
            'title' => $this->title,
            'text' => $this->text,
            'author' => $this->author,
            'published' => $this->published
        ];
        $serializedData = serialize($data);
        file_put_contents('test_text_file' . self::FILE_EXTENSION, $serializedData);
        return $this->slug;
    }
    public static function loadText(string $slug): ?TelegraphText {
        $filename = 'test_text_file' . self::FILE_EXTENSION;
        if (!file_exists($filename)) {
            return null;
        }
        $serializedData = file_get_contents($filename);
        if ($serializedData === false) {
            return null;
        }
        $data = unserialize($serializedData);
        $instance = new self($data['title'], $data['author'], $data['text']);
        $instance->published = $data['published'];
        return $instance;
    }
    
    public function editText(string $title, string $text): void {
        $this->title = str_replace(' ', '-', $title);
        $this->text = $text;
    }
}

$telegraphText = new TelegraphText('Пример подзаголовка', 'Артур Зеленко', 'Пример текста.');

$telegraphText->editText('Обновленный подзаголовок', 'Обновленный текст.');
$slug = $telegraphText->storeText();

$loadedText = TelegraphText::loadText($slug);
if ($loadedText !== null) {
    echo $loadedText->title . PHP_EOL;
}