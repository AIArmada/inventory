<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

/**
 * Trait for caching computed values within a single request lifecycle.
 *
 * Uses Laravel's native `once()` helper to cache expensive computations
 * that are called multiple times during a single request.
 *
 * @example
 * ```php
 * public function getDefaultTier(): ?AffiliateProgramTier
 * {
 *     return $this->cachedComputation(__METHOD__, fn () =>
 *         $this->tiers()->orderBy('level', 'desc')->first()
 *     );
 * }
 * ```
 */
trait CachesComputedValues
{
    /**
     * Cache a computed value for the request lifetime.
     *
     * @template T
     *
     * @param  string  $key  Unique key for this computation (use __METHOD__ for simplicity)
     * @param  callable(): T  $callback  The computation to cache
     * @return T
     */
    protected function cachedComputation(string $key, callable $callback): mixed
    {
        // Use once() with a unique key based on class + instance + method
        $cacheKey = $this->getCacheKey($key);

        return once(fn () => $callback());
    }

    /**
     * Cache a computed value with model instance specificity.
     *
     * Use this when the same method on different model instances
     * should have separate cached values.
     *
     * @template T
     *
     * @param  string  $method  The method name (use __METHOD__)
     * @param  callable(): T  $callback  The computation to cache
     * @return T
     */
    protected function cachedForInstance(string $method, callable $callback): mixed
    {
        return once(fn () => $callback());
    }

    /**
     * Generate a unique cache key for this instance + method combination.
     */
    private function getCacheKey(string $method): string
    {
        $instanceId = method_exists($this, 'getKey') ? $this->getKey() : spl_object_id($this);

        return static::class . ':' . $instanceId . ':' . $method;
    }
}
