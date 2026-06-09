<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

use AIArmada\Inventory\Enums\CostingMethod;
use Illuminate\Database\Eloquent\Model;

interface CostingMethodInterface
{
    public function supports(): CostingMethod;

    /**
     * @return array{quantity: int, value: int, average_cost: int}
     */
    public function calculateValuation(Model $model, ?string $locationId = null): array;

    /**
     * @return array{consumed: int, cost: int, layers: array<int, array{layer_id: string, quantity: int, unit_cost: int}>}
     */
    public function consume(Model $model, int $quantity, ?string $locationId = null): array;

    public function estimateCogs(Model $model, int $quantity, ?string $locationId = null): int;
}
