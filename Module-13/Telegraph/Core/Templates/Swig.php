<?php
namespace Telegraph\Core\Templates;
use Telegraph\Entities\TelegraphText;
use Telegraph\Entities\View;
use Telegraph\Interfaces\IRender;
use Telegraph\Entities\Storage;
use Telegraph\Entities\FileStorage;
class Swig extends View implements IRender
{
    public function render(TelegraphText $telegraphText): string
    {
        $templatePath = __DIR__ . DIRECTORY_SEPARATOR . $this->templateName;
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }
        $templateContent = file_get_contents($templatePath);
        if ($templateContent === false) {
            throw new \RuntimeException("Unable to read template file: {$templatePath}");
        }
        foreach ($this->variables as $key) {
            $value = $this->getVariableValue($telegraphText, $key);
            $templateContent = str_replace('{{' . $key . '}}', htmlspecialchars($value), $templateContent);
        }
        return $templateContent;
    }
}
