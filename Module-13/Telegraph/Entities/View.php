<?php
namespace Telegraph\Entities;


abstract class View
{
    protected string $templateName;
    protected array $variables = [];
    abstract public function render(TelegraphText $telegraphText): string;
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
public function displayTemplateContent(): void 
    {
        $templatePath = __DIR__ . '/../Core/Templates/' . $this->templateName;
        $content = file_exists($templatePath) ? file_get_contents($templatePath) : 'Template not found';
        echo "Template content for {$this->templateName}:\n{$content}\n";
    }

}    
