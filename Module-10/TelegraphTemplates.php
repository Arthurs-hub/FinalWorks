<?php

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

    protected function getVariableValue(TelegraphText $telegraphText, string $variable): string
    {
        $getter = 'get' . ucfirst($variable);
        if (method_exists($telegraphText, $getter)) {
            return $telegraphText->$getter();
        }
        return '';
    }
}

class Swig extends View
{
    public function render(TelegraphText $telegraphText): string
    {
        $templatePath = sprintf('templates/%s.swig.txt', $this->templateName);
        $templateContent = file_get_contents($templatePath);

        foreach ($this->variables as $key) {
            $templateContent = str_replace('{{' . $key . '}}', $this->getVariableValue($telegraphText, $key), $templateContent);
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
            $templateContent = str_replace('$$' . $key . '$$', $this->getVariableValue($telegraphText, $key), $templateContent);
        }

        return $templateContent;
    }
}

$telegraphText = new TelegraphText('Vasya', 'Some slug');
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
