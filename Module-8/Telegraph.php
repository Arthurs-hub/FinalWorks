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
        }
