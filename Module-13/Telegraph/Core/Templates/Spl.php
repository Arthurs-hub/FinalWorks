<?php
namespace Telegraph\Core\Templates;
use Telegraph\Entities\TelegraphText;
use Telegraph\Entities\View;
use Telegraph\Interfaces\IRender;
use Telegraph\Entities\Storage;
use Telegraph\Entities\FileStorage;
class Spl extends View implements IRender
{
    public function render(TelegraphText $telegraphText): string
    {
        $templatePath = sprintf('%s/%s', __DIR__, $this->templateName);
        
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }
        $templateContent = file_get_contents($templatePath);
        if ($templateContent === false) {
            throw new \RuntimeException("Unable to read template file: {$templatePath}");
        }
        foreach ($this->variables as $key) {
            $value = $this->getVariableValue($telegraphText, $key);
           
            $templateContent = str_replace('$' . $key . '$', $this->getVariableValue($telegraphText, $key), $templateContent);
        }
        return $templateContent;
    }
}

