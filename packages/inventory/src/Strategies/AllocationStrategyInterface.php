<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Strategies;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TOrderModel of Model
 */
interface AllocationStrategyInterface
{
    /**
     * Get the strategy identifier.
     */
    public function name(): string;

    /**
     * Get human-readable label.
     */
    public function label(): string;

    /**
     * Get strategy description.
     */
    public function description(): string;

    /**
     * Allocate inventory according to the strategy.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allocate(Model $model, int $quantity, ?AllocationContext $context = null): array;

    /**
     * Check if the strategy can fulfill the requested quantity.
     */
    public function canFulfill(Model $model, int $quantity, ?AllocationContext $context = null): bool;

    /**
     * Get recommended allocation order for display.
     *
     * @return Collection<int, TOrderModel>
     */
    public function getRecommendedOrder(Model $model, ?AllocationContext $context = null): Collection;
}
