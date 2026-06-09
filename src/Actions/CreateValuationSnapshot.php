<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryValuationSnapshot;
use AIArmada\Inventory\Services\Costing\ValuationService;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateValuationSnapshot
{
    use AsAction;

    public function __construct(
        private readonly ValuationService $valuationService,
    ) {}

    public function handle(
        CostingMethod $method,
        ?string $locationId = null,
        ?Carbon $snapshotDate = null,
    ): InventoryValuationSnapshot {
        return $this->valuationService->createSnapshot(
            method: $method,
            locationId: $locationId,
            snapshotDate: $snapshotDate,
        );
    }
}
