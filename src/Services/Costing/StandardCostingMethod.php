<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services\Costing;

use AIArmada\Inventory\Contracts\CostingMethodInterface;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

final class StandardCostingMethod implements CostingMethodInterface
{
    public function __construct(
        private readonly StandardCostService $service,
    ) {}

    public function supports(): CostingMethod
    {
        return CostingMethod::Standard;
    }

    /** @return array{quantity: int, value: int, average_cost: int} */
    public function calculateValuation(Model $model, ?string $locationId = null): array
    {
        $unitCost = $this->service->getCurrentCostValue($model);
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
        );

        if ($locationId !== null) {
            $locationExists = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereKey($locationId)
                ->exists();

            if (! $locationExists) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }

            $query->where('location_id', $locationId);
        }

        $quantity = (int) $query->sum('quantity_on_hand');
        $standardCost = $unitCost ?? 0;

        return [
            'quantity' => $quantity,
            'value' => $quantity * $standardCost,
            'average_cost' => $standardCost,
        ];
    }

    public function consume(Model $model, int $quantity, ?string $locationId = null): array
    {
        throw new RuntimeException('Standard cost does not support layer-level consume.');
    }

    public function estimateCogs(Model $model, int $quantity, ?string $locationId = null): int
    {
        $unitCost = $this->service->getCurrentCostValue($model);

        return $unitCost !== null ? $unitCost * $quantity : 0;
    }
}
