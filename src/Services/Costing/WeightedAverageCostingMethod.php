<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services\Costing;

use AIArmada\Inventory\Contracts\CostingMethodInterface;
use AIArmada\Inventory\Enums\CostingMethod;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class WeightedAverageCostingMethod implements CostingMethodInterface
{
    public function __construct(
        private readonly WeightedAverageCostService $service,
    ) {}

    public function supports(): CostingMethod
    {
        return CostingMethod::WeightedAverage;
    }

    /** @return array{quantity: int, value: int, average_cost: int} */
    public function calculateValuation(Model $model, ?string $locationId = null): array
    {
        return $this->service->calculateValuation($model, $locationId);
    }

    /** @return array{consumed: int, cost: int, layers: array<int, array{layer_id: string, quantity: int, unit_cost: int}>} */
    public function consume(Model $model, int $quantity, ?string $locationId = null): array
    {
        throw new RuntimeException('Weighted average does not support layer-level consume.');
    }

    public function estimateCogs(Model $model, int $quantity, ?string $locationId = null): int
    {
        $valuation = $this->service->calculateValuation($model, $locationId);

        return $valuation['average_cost'] * $quantity;
    }
}
