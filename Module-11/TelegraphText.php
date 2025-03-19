<?php

class TelegraphText
{
    private $text = '';
    private $title = '';
    private $author = '';
    private $published = '';
    private $slug = '';

    public function __set($name, $value)
    {
        switch ($name) {
            case 'author':
                if (strlen($value) > 120) {
                    throw new Exception("Author name cannot be longer than 120 characters.");
                }
                $this->author = $value;
                break;
            case 'slug':
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                    throw new Exception("Slug can only contain letters, numbers, hyphens, and underscores.");
                }
                $this->slug = $value;
                break;
            case 'published':
                if (strtotime($value) < time()) {
                    throw new Exception("Published date must be greater than or equal to the current date.");
                }
                $this->published = $value;
                break;
            case 'text':
                $this->storeText($value);
                break;
            default:
                throw new Exception("Invalid property: " . $name);
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'author':
                return $this->author;
            case 'slug':
                return $this->slug;
            case 'published':
                return $this->published;
            case 'text':
                return $this->loadText();
            default:
                throw new Exception("Invalid property: " . $name);
        }
    }
}