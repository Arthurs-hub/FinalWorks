<?php

class TelegraphText {
    public string $title;
    public string $text;
    public string $slug;

    public function __construct(string $title, string $text) {
        $this->title = $title;
        $this->text = $text;
        $this->slug = 'Some slug';
    }

    public function editText(string $title, string $text): void {
        $this->title = $title;
        $this->text = $text;
    }
}