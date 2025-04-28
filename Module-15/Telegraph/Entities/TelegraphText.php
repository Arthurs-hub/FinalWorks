<?php

namespace Telegraph\Entities;

class TelegraphText
{
    private string $title;
    private string $text;
    private string $slug;

    public function __construct(string $title, string $text)
    {
        if (trim($text) === '') {
            throw new \Exception('Текст не может быть пустым. Пожалуйста, введите текст.');
        }

        if (strlen($text) < 1 || strlen($text) > 500) {
            throw new \Exception('Длина текста должна быть от 1 до 500 символов.');
        }

        $this->title = $title;
        $this->text = $text;
        $this->slug = preg_replace('/[^a-z0-9]+/i', '-', $title) . '-' . time();
    }

    public function editText(string $title, string $text): void
    {
        if (strlen($text) < 1 || strlen($text) > 500) {
            throw new \Exception('Длина текста должна быть от 1 до 500 символов.');
        }
        $this->title = $title;
        $this->text = $text;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getAuthor(): string
    {
        return 'Author Name';
    }

    public function getPublishedDate(): string
    {
        return '2025-04-15';
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function setAuthor(string $author): void
    {

    }
    public function setPublished(string $published): void
    {

    }

}
