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

class Swig extends View
{
    public function render(TelegraphText $telegraphText): string
    {
        $templatePath = sprintf('templates/%s.swig.txt', $this->templateName);
        $templateContent = file_get_contents($templatePath);

        foreach ($this->variables as $key) {
            $templateContent = str_replace('{{' . $key . '}}', $telegraphText->$key, $templateContent);
        }

        return $templateContent;
    }
}

class Spl extends View
{
    public function render(TelegraphText $telegraphText): string
    {
        $templatePath = sprintf('templates/%s.spl.txt', $this->templateName);
        $templateContent = file_get_contents($templatePath);

        foreach ($this->variables as $key) {
            $templateContent = str_replace('$$' . $key . '$$', $telegraphText->$key, $templateContent);
        }

        return $templateContent;
    }
}

$telegraphText = new TelegraphText('Vasya', 'Some-slug');
$telegraphText->editText('Some title', 'Some text');

$swig = new Swig('telegraph_text');
$swig->addVariablesToTemplate(['slug', 'text']);

$spl = new Spl('telegraph_text');
$spl->addVariablesToTemplate(['slug', 'title', 'text']);

$templateEngines = [$swig, $spl];
foreach ($templateEngines as $engine) {
    if ($engine instanceof IRender) {
        echo $engine->render($telegraphText) . PHP_EOL;
    } else {
        echo 'Template engine does not support render interface' . PHP_EOL;
    }
}
