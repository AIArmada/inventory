<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services\Costing;

use AIArmada\Inventory\Contracts\CostingMethodInterface;
use AIArmada\Inventory\Enums\CostingMethod;
use Illuminate\Database\Eloquent\Model;

final class FifoCostingMethod implements CostingMethodInterface
{
    public function __construct(
        private readonly FifoCostService $service,
    ) {}

    public function supports(): CostingMethod
    {
        return CostingMethod::Fifo;
    }

    /** @return array{quantity: int, value: int, average_cost: int, layers?: int} */
    public function calculateValuation(Model $model, ?string $locationId = null): array
    {
        return $this->service->calculateValuation($model, $locationId);
    }

    /** @return array{consumed: int, cost: int, layers: array<int, array{layer_id: string, quantity: int, unit_cost: int}>} */
    public function consume(Model $model, int $quantity, ?string $locationId = null): array
    {
        return $this->service->consume($model, $quantity, $locationId);
    }

    public function estimateCogs(Model $model, int $quantity, ?string $locationId = null): int
    {
        return $this->service->estimateCogs($model, $quantity, $locationId);
    }
}
