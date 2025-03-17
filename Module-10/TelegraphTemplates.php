<?php

class TelegraphText
{
    public string $title;
    public string $text;
    public string $slug;

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
}

interface IRender
{
    public function render(TelegraphText $telegraphText): string;
}

abstract class View implements IRender
{
    protected string $templateName;
    protected array $variables = [];

    public function __construct(string $templateName)
    {
        $this->templateName = $templateName;
    }

    public function addVariablesToTemplate(array $variables): void
    {
        $this->variables = $variables;
    }
}