<?php

declare(strict_types=1);

namespace AIArmada\Docs\Numbering;

use AIArmada\Docs\Numbering\Contracts\DocumentNumberStrategy;
use AIArmada\Docs\Numbering\Strategies\DefaultNumberStrategy;
use Illuminate\Support\Str;

final class ConfiguredNumberStrategyRegistry extends NumberStrategyRegistry
{
    public function __construct()
    {
        // Auto-register strategies for all document types
        $types = config('docs.types', []);

        foreach ($types as $docType => $typeConfig) {
            $strategy = $this->resolveStrategy($docType, $typeConfig);
            $this->register($docType, $strategy);
        }
    }

    /**
     * Resolve the numbering strategy for a document type.
     *
     * Priority order:
     * 1. Explicit strategy in config: 'numbering.strategy' => CustomStrategy::class
     * 2. Convention-based auto-detection: App\Numbering\{DocType}NumberStrategy
     * 3. Default strategy: DefaultNumberStrategy
     */
    /**
     * @param  array<string, mixed>  $typeConfig
     */
    protected function resolveStrategy(string $docType, array $typeConfig): DocumentNumberStrategy
    {
        // 1. Check if explicitly configured in config
        $explicitStrategy = $typeConfig['numbering']['strategy'] ?? null;

        if ($explicitStrategy) {
            $strategyClass = $explicitStrategy;

            if (is_string($strategyClass) && class_exists($strategyClass) && is_subclass_of($strategyClass, DocumentNumberStrategy::class)) {
                return app($strategyClass);
            }
        }

        // 2. Auto-detect convention-based strategy: App\Numbering\{DocType}NumberStrategy
        $customClass = 'App\\Numbering\\' . Str::studly($docType) . 'NumberStrategy';

        if (class_exists($customClass) && is_subclass_of($customClass, DocumentNumberStrategy::class)) {
            return app($customClass);
        }

        // 3. Fall back to default config-based strategy
        return app(DefaultNumberStrategy::class);
    }
}
