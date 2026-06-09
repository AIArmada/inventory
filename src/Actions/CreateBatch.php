<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Services\Batch\BatchService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateBatch
{
    use AsAction;

    public function __construct(
        private readonly BatchService $batchService,
    ) {}

    public function handle(
        Model $model,
        string $batchNumber,
        string $locationId,
        int $quantity,
        ?Carbon $expiresAt = null,
        ?Carbon $manufacturedAt = null,
        ?string $lotNumber = null,
        ?int $unitCostMinor = null,
        ?string $supplierId = null,
        ?string $purchaseOrderNumber = null,
    ): InventoryBatch {
        return $this->batchService->createBatch(
            model: $model,
            batchNumber: $batchNumber,
            locationId: $locationId,
            quantity: $quantity,
            expiresAt: $expiresAt,
            manufacturedAt: $manufacturedAt,
            lotNumber: $lotNumber,
            unitCostMinor: $unitCostMinor,
            supplierId: $supplierId,
            purchaseOrderNumber: $purchaseOrderNumber,
        );
    }
}
