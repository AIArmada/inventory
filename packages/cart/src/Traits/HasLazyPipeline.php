<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineContext;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineResult;
use AIArmada\Cart\Conditions\Pipeline\LazyConditionPipeline;

/**
 * Provides lazy evaluation and memoization for condition pipeline calculations.
 *
 * This trait optimizes cart total calculations by caching results and only
 * recomputing when the cart state changes (items added/removed, conditions changed).
 */
trait HasLazyPipeline
{
    private ?LazyConditionPipeline $lazyPipeline = null;

    private bool $lazyPipelineEnabled = true;

    /**
     * Enable or disable lazy pipeline evaluation.
     */
    public function withLazyPipeline(bool $enabled = true): static
    {
        $this->lazyPipelineEnabled = $enabled;

        if (! $enabled) {
            $this->lazyPipeline = null;
        }

        return $this;
    }

    /**
     * Disable lazy pipeline (use standard evaluation).
     */
    public function withoutLazyPipeline(): static
    {
        return $this->withLazyPipeline(false);
    }

    /**
     * Check if lazy pipeline is enabled.
     */
    public function isLazyPipelineEnabled(): bool
    {
        return $this->lazyPipelineEnabled && config('cart.performance.lazy_pipeline', true);
    }

    /**
     * Invalidate the lazy pipeline cache.
     *
     * Call this when cart state changes (items, conditions, etc.)
     */
    public function invalidatePipelineCache(): void
    {
        if ($this->lazyPipeline !== null) {
            $this->lazyPipeline->invalidate();
        }

        $this->lazyPipeline = null;
    }

    /**
     * Get lazy pipeline statistics for monitoring/debugging.
     *
     * @return array{cached_phases: int, is_stale: bool, has_full_result: bool}
     */
    public function getPipelineCacheStats(): array
    {
        if ($this->lazyPipeline === null) {
            return [
                'cached_phases' => 0,
                'is_stale' => true,
                'has_full_result' => false,
            ];
        }

        return $this->lazyPipeline->getCacheStats();
    }

    /**
     * Get or create lazy pipeline instance.
     *
     * This ensures dynamic conditions are evaluated before building the pipeline context.
     */
    protected function getLazyPipeline(): LazyConditionPipeline
    {
        if ($this->lazyPipeline === null || ! $this->lazyPipeline->isCached()) {
            // Ensure dynamic conditions are evaluated before building pipeline
            $this->evaluateDynamicConditionsIfDirty();

            $context = ConditionPipelineContext::fromCart($this);
            $this->lazyPipeline = new LazyConditionPipeline($context);
        }

        return $this->lazyPipeline;
    }

    /**
     * Evaluate pipeline with lazy caching if enabled.
     */
    protected function evaluatePipelineWithCaching(): ConditionPipelineResult
    {
        if (! $this->isLazyPipelineEnabled()) {
            return $this->evaluateConditionPipeline();
        }

        return $this->getLazyPipeline()->getFullResult();
    }

    /**
     * Get subtotal using lazy pipeline if enabled.
     */
    protected function getSubtotalWithLazyPipeline(): int
    {
        if (! $this->isLazyPipelineEnabled()) {
            // Ensure dynamic conditions are evaluated before pipeline
            $this->evaluateDynamicConditionsIfDirty();

            return $this->evaluateConditionPipeline()->subtotal();
        }

        return $this->getLazyPipeline()->getSubtotal();
    }

    /**
     * Get total using lazy pipeline if enabled.
     */
    protected function getTotalWithLazyPipeline(): int
    {
        if (! $this->isLazyPipelineEnabled()) {
            // Ensure dynamic conditions are evaluated before pipeline
            $this->evaluateDynamicConditionsIfDirty();

            return $this->evaluateConditionPipeline()->total();
        }

        return $this->getLazyPipeline()->getTotal();
    }
}
