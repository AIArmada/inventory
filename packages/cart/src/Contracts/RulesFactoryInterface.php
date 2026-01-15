<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use InvalidArgumentException;

/**
 * Interface for creating rule closures for dynamic conditions.
 *
 * This enables persistence of dynamic conditions by storing their metadata
 * and reconstructing the rule closures when needed. Useful for:
 * - Inventory availability checks
 * - Customer eligibility rules
 * - Time-based conditions
 * - Cart value thresholds
 * - Product/category restrictions
 */
interface RulesFactoryInterface
{
    /**
     * Create rules for a dynamic condition by factory key.
     *
     * @param  string  $key  The rule factory key that identifies which rules to create
     * @param  array<string, mixed>  $metadata  Additional condition metadata that may be needed for rule creation
     * @return array<callable> Array of rule closures
     *
     * @throws InvalidArgumentException When the factory key is not supported
     */
    public function createRules(string $key, array $metadata = []): array;

    /**
     * Check if factory can create rules for the given key.
     *
     * @param  string  $key  The rule factory key to check
     * @return bool True if the factory can create rules for this key
     */
    public function canCreateRules(string $key): bool;

    /**
     * Get all available rule factory keys.
     *
     * @return array<string> List of supported rule factory keys
     */
    public function getAvailableKeys(): array;
}
