<?php

namespace Telegraph\Entities;

class TelegraphText
{
    private string $title;
    private string $text;
    private string $slug;

    public function __construct(string $title, string $text)
    {
        $this->title = $title;
        $this->text = $text;
        $this->slug = 'Some slug';
    }

    public function editText(string $title, string $text): void
    {
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
