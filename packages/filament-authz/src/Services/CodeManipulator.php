<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use Illuminate\Support\Facades\File;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

class CodeManipulator
{
    protected string $path;

    protected string $originalContent;

    protected ?string $modifiedContent = null;

    /**
     * @var array<string, string>
     */
    protected array $history = [];

    protected Parser $parser;

    protected PrettyPrinter $printer;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->originalContent = File::get($path);
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->printer = new PrettyPrinter();
    }

    /**
     * Create a new manipulator for a file.
     */
    public static function make(string $path): self
    {
        return new self($path);
    }

    /**
     * Add a use statement.
     */
    public function addUse(string $class): self
    {
        $content = $this->getCurrentContent();

        // Check if already imported
        if (str_contains($content, "use {$class};")) {
            return $this;
        }

        // Find import section
        $pattern = '/^(namespace [^;]+;)(\s*)/sm';
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, "$1$2\nuse {$class};", $content, 1);
        }

        $this->setContent($content);

        return $this;
    }

    /**
     * Add a trait to a class.
     */
    public function addTrait(string $traitClass): self
    {
        $content = $this->getCurrentContent();

        $traitName = class_basename($traitClass);

        // Check if trait already used (looking inside class body for `use TraitName;`)
        if (preg_match("/class\s+\w+[^{]*\{[^}]*use\s+[^;]*\b{$traitName}\b/s", $content)) {
            return $this;
        }

        // Add import at file level
        $this->addUse($traitClass);
        $content = $this->getCurrentContent();

        // Check if there's already a trait use statement inside the class
        // Pattern: after `class X {` find `use Something;` inside the class
        $classPattern = '/(class\s+\w+[^{]*\{)(\s*)(use\s+[^;]+;)?/s';

        if (preg_match($classPattern, $content, $matches)) {
            if (! empty($matches[3])) {
                // Append to existing trait use statement
                $existingUse = mb_rtrim($matches[3], ';');
                $newUse = $existingUse.", {$traitName};";
                $content = str_replace($matches[3], $newUse, $content);
            } else {
                // Add new use statement after class opening brace
                $classOpening = $matches[1];
                $whitespace = $matches[2] ?: "\n";
                $replacement = $classOpening.$whitespace."    use {$traitName};\n";
                $content = preg_replace($classPattern, $replacement, $content, 1);
            }
        }

        $this->setContent($content);

        return $this;
    }

    /**
     * Set a property value.
     */
    public function setProperty(string $name, mixed $value): self
    {
        $content = $this->getCurrentContent();
        $valueStr = $this->valueToString($value);

        // Check if property exists and update it
        $pattern = "/^(\s*)(protected|private|public)\s+(static\s+)?(\??\w+\s+)?\\\${$name}\s*=\s*[^;]+;/m";
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, "$1$2 $3$4\${$name} = {$valueStr};", $content, 1);
        } else {
            // Property doesn't exist, add it
            $content = preg_replace(
                '/(class\s+\w+[^{]*{)/',
                "$1\n    protected \${$name} = {$valueStr};",
                $content,
                1
            );
        }

        $this->setContent($content);

        return $this;
    }

    /**
     * Add a method to the class.
     */
    public function addMethod(string $name, string $body, string $visibility = 'public', bool $static = false): self
    {
        $content = $this->getCurrentContent();

        // Check if method already exists
        if (preg_match("/function\s+{$name}\s*\(/", $content)) {
            return $this;
        }

        $staticKeyword = $static ? 'static ' : '';
        $method = <<<PHP

    {$visibility} {$staticKeyword}function {$name}()
    {
{$body}
    }
PHP;

        // Add before last closing brace
        $content = preg_replace('/}(\s*)$/', $method."\n}$1", $content, 1);

        $this->setContent($content);

        return $this;
    }

    /**
     * Append to an array property.
     */
    public function appendToArray(string $property, string $key, mixed $value): self
    {
        $content = $this->getCurrentContent();
        $valueStr = $this->valueToString($value);

        // Find the array property and add to it
        $pattern = "/^(\s*)(protected|private|public)\s+(static\s+)?array\s+\\\${$property}\s*=\s*\[([^\]]*)\];/m";

        if (preg_match($pattern, $content, $matches)) {
            $arrayContent = $matches[4];

            // Check if key already exists
            if ($key !== null && str_contains($arrayContent, "'{$key}'")) {
                return $this;
            }

            if ($key !== null) {
                $newEntry = "'{$key}' => {$valueStr}";
            } else {
                $newEntry = $valueStr;
            }

            // Add the new entry
            if (mb_trim($arrayContent) === '') {
                $newArrayContent = "\n        {$newEntry},\n    ";
            } else {
                $newArrayContent = mb_rtrim($arrayContent).",\n        {$newEntry},\n    ";
            }

            $replacement = "{$matches[1]}{$matches[2]} {$matches[3]}array \${$property} = [{$newArrayContent}];";
            $content = preg_replace($pattern, $replacement, $content, 1);
        }

        $this->setContent($content);

        return $this;
    }

    /**
     * Get the diff between original and modified content.
     */
    public function diff(): string
    {
        $original = explode("\n", $this->originalContent);
        $modified = explode("\n", $this->getCurrentContent());

        $diff = [];
        $maxLines = max(count($original), count($modified));

        for ($i = 0; $i < $maxLines; $i++) {
            $origLine = $original[$i] ?? '';
            $modLine = $modified[$i] ?? '';

            if ($origLine !== $modLine) {
                if ($origLine !== '') {
                    $diff[] = '- '.$origLine;
                }
                if ($modLine !== '') {
                    $diff[] = '+ '.$modLine;
                }
            }
        }

        return implode("\n", $diff);
    }

    /**
     * Undo the last change.
     */
    public function undo(): self
    {
        if (! empty($this->history)) {
            $this->modifiedContent = array_pop($this->history);
        }

        return $this;
    }

    /**
     * Save changes to the file.
     */
    public function save(): bool
    {
        $content = $this->getCurrentContent();

        if ($content === $this->originalContent) {
            return false;
        }

        return File::put($this->path, $content) !== false;
    }

    /**
     * Preview the changes without saving.
     */
    public function preview(): string
    {
        return $this->getCurrentContent();
    }

    /**
     * Check if class contains a specific trait.
     */
    public function containsTrait(string $traitName): bool
    {
        $content = $this->getCurrentContent();
        $baseName = class_basename($traitName);

        return (bool) preg_match("/use[^;]*\\b{$baseName}\\b/s", $content);
    }

    /**
     * Check if class contains a specific method.
     */
    public function containsMethod(string $methodName): bool
    {
        $content = $this->getCurrentContent();

        return (bool) preg_match("/function\s+{$methodName}\s*\(/", $content);
    }

    /**
     * Check if file contains a use statement.
     */
    public function containsUse(string $class): bool
    {
        return str_contains($this->getCurrentContent(), "use {$class};");
    }

    /**
     * Get the current content.
     */
    protected function getCurrentContent(): string
    {
        return $this->modifiedContent ?? $this->originalContent;
    }

    /**
     * Set the modified content.
     */
    protected function setContent(string $content): void
    {
        if ($this->modifiedContent !== null) {
            $this->history[] = $this->modifiedContent;
        }
        $this->modifiedContent = $content;
    }

    /**
     * Convert a value to a string representation.
     */
    protected function valueToString(mixed $value): string
    {
        if (is_string($value)) {
            return "'{$value}'";
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_array($value)) {
            $items = [];
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);

            foreach ($value as $k => $v) {
                $itemValue = $this->valueToString($v);
                if ($isAssoc) {
                    $items[] = "'{$k}' => {$itemValue}";
                } else {
                    $items[] = $itemValue;
                }
            }

            return '['.implode(', ', $items).']';
        }

        return (string) $value;
    }
}
