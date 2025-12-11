<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\PolicyType;
use Illuminate\Support\Str;

class PolicyGeneratorService
{
    protected string $stubsPath;

    public function __construct()
    {
        $this->stubsPath = config('filament-authz.policies.stubs_path')
            ?? __DIR__ . '/../../stubs/policies';
    }

    /**
     * Generate a policy for the given model.
     *
     * @param  array<string, mixed>  $options
     */
    public function generate(
        string $modelClass,
        PolicyType $type = PolicyType::Basic,
        array $options = []
    ): GeneratedPolicy {
        $modelBasename = class_basename($modelClass);
        $policyClass = $options['className'] ?? "{$modelBasename}Policy";
        $namespace = $options['namespace'] ?? 'App\\Policies';
        $userModel = $options['userModel'] ?? config('filament-authz.user_model', 'App\\Models\\User');
        $permissionPrefix = $options['permissionPrefix'] ?? Str::snake($modelBasename);

        $stub = $this->getStub($type);
        $methods = $this->generateMethods($type, $userModel, $modelClass, $permissionPrefix);

        $content = $this->populateStub($stub, [
            'namespace' => $namespace,
            'class' => $policyClass,
            'userModel' => $userModel,
            'user' => class_basename($userModel),
            'model' => $modelClass,
            'modelVariable' => Str::camel($modelBasename),
            'modelName' => $modelBasename,
            'methods' => $methods,
            'permissionPrefix' => $permissionPrefix,
        ]);

        $path = $options['path'] ?? app_path("Policies/{$policyClass}.php");

        return new GeneratedPolicy(
            path: $path,
            content: $content,
            metadata: [
                'type' => $type->value,
                'model' => $modelClass,
                'class' => "{$namespace}\\{$policyClass}",
            ]
        );
    }

    /**
     * Get the stub content for a policy type.
     */
    protected function getStub(PolicyType $type): string
    {
        $stubFile = match ($type) {
            PolicyType::Basic => 'basic.stub',
            PolicyType::Hierarchical => 'hierarchical.stub',
            PolicyType::Contextual => 'contextual.stub',
            PolicyType::Temporal => 'temporal.stub',
            PolicyType::Abac => 'abac.stub',
            PolicyType::Composite => 'composite.stub',
        };

        $path = "{$this->stubsPath}/{$stubFile}";

        if (! file_exists($path)) {
            // Fall back to basic stub
            return $this->getBasicStub();
        }

        return file_get_contents($path) ?: $this->getBasicStub();
    }

    /**
     * Generate policy methods.
     */
    protected function generateMethods(
        PolicyType $type,
        string $userModel,
        string $modelClass,
        string $permissionPrefix
    ): string {
        $methods = [];
        $abilities = $this->getAbilities();

        foreach ($abilities as $ability) {
            $methods[] = $this->generateMethod(
                $ability,
                $type,
                $userModel,
                $modelClass,
                $permissionPrefix
            );
        }

        return implode("\n\n", $methods);
    }

    /**
     * Generate a single method.
     */
    protected function generateMethod(
        string $ability,
        PolicyType $type,
        string $userModel,
        string $modelClass,
        string $permissionPrefix
    ): string {
        $isSingleParam = in_array($ability, PolicyType::singleParamMethods());
        $isOwnerAware = in_array($ability, PolicyType::ownerAwareMethods());
        $userBasename = class_basename($userModel);
        $modelBasename = class_basename($modelClass);
        $modelVariable = Str::camel($modelBasename);
        $permission = "{$permissionPrefix}.{$ability}";

        // Generate docblock
        $docblock = $this->getDocblock($ability, $modelBasename);

        // Generate signature
        $params = "\${$userBasename} \$user";
        if (! $isSingleParam) {
            $params .= ", {$modelBasename} \${$modelVariable}";
        }

        // Generate body based on policy type
        $body = $this->getMethodBody($type, $ability, $permission, $modelVariable, $isSingleParam, $isOwnerAware);

        return <<<PHP
    {$docblock}
    public function {$ability}({$params}): bool
    {
{$body}
    }
PHP;
    }

    /**
     * Get method body based on policy type.
     */
    protected function getMethodBody(
        PolicyType $type,
        string $ability,
        string $permission,
        string $modelVariable,
        bool $isSingleParam,
        bool $isOwnerAware
    ): string {
        $indent = '        ';

        return match ($type) {
            PolicyType::Basic => "{$indent}return \$user->can('{$permission}');",

            PolicyType::Hierarchical => "{$indent}return \$this->checkWithHierarchy(\$user, '{$permission}');",

            PolicyType::Contextual => $isOwnerAware && ! $isSingleParam
            ? "{$indent}// Owner can always {$ability} their own records\n{$indent}if (\$this->isOwner(\$user, \${$modelVariable})) {\n{$indent}    return true;\n{$indent}}\n\n{$indent}return \$this->checkInContext(\$user, '{$permission}', \${$modelVariable});"
            : "{$indent}return \$this->checkInContext(\$user, '{$permission}'" . ($isSingleParam ? '' : ", \${$modelVariable}") . ');',

            PolicyType::Temporal => "{$indent}// Check for active temporal permission\n{$indent}if (\$this->hasTemporalGrant(\$user, '{$permission}')) {\n{$indent}    return true;\n{$indent}}\n\n{$indent}return \$user->can('{$permission}');",

            PolicyType::Abac, PolicyType::Composite => $isSingleParam
            ? "{$indent}return \$this->evaluatePolicy(\$user, '{$ability}');"
            : "{$indent}return \$this->evaluatePolicy(\$user, '{$ability}', \${$modelVariable});",
        };
    }

    /**
     * Get docblock for a method.
     */
    protected function getDocblock(string $ability, string $modelName): string
    {
        $description = match ($ability) {
            'viewAny' => "Determine if the user can view any {$modelName} models.",
            'view' => "Determine if the user can view the {$modelName}.",
            'create' => "Determine if the user can create {$modelName} models.",
            'update' => "Determine if the user can update the {$modelName}.",
            'delete' => "Determine if the user can delete the {$modelName}.",
            'restore' => "Determine if the user can restore the {$modelName}.",
            'forceDelete' => "Determine if the user can permanently delete the {$modelName}.",
            default => "Determine if the user can {$ability} the {$modelName}.",
        };

        return <<<DOCBLOCK
/**
     * {$description}
     */
DOCBLOCK;
    }

    /**
     * Get the list of abilities to generate.
     *
     * @return array<string>
     */
    protected function getAbilities(): array
    {
        return config('filament-authz.policies.methods', [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
            'restore',
            'forceDelete',
        ]);
    }

    /**
     * Populate stub with replacements.
     *
     * @param  array<string, string|array<string>>  $replacements
     */
    protected function populateStub(string $stub, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $stub = str_replace("{{ {$key} }}", $value, $stub);
        }

        return $stub;
    }

    /**
     * Get the basic policy stub as fallback.
     */
    protected function getBasicStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use {{ userModel }};
use {{ model }};
use Illuminate\Auth\Access\HandlesAuthorization;

class {{ class }}
{
    use HandlesAuthorization;

{{ methods }}
}
STUB;
    }
}

/**
 * Value object representing a generated policy.
 */
readonly class GeneratedPolicy
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $path,
        public string $content,
        public array $metadata = [],
    ) {}

    /**
     * Write the policy to disk.
     */
    public function write(bool $force = false): bool
    {
        if (! $force && file_exists($this->path)) {
            return false;
        }

        $directory = dirname($this->path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return (bool) file_put_contents($this->path, $this->content);
    }

    /**
     * Get the diff if the file already exists.
     */
    public function getDiff(): ?string
    {
        if (! file_exists($this->path)) {
            return null;
        }

        $existing = file_get_contents($this->path);

        if ($existing === $this->content) {
            return null;
        }

        // Simple diff - just show that files are different
        return 'File exists and would be modified.';
    }
}
