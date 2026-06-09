<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Services\Serial\SerialService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

final class RecordSerial
{
    use AsAction;

    public function __construct(
        private readonly SerialService $serialService,
    ) {}

    public function handle(
        Model $model,
        string $serialNumber,
        ?string $locationId = null,
        ?string $batchId = null,
        SerialCondition $condition = SerialCondition::New,
        ?int $unitCostMinor = null,
        ?Carbon $warrantyExpiresAt = null,
        ?string $userId = null,
    ): InventorySerial {
        return $this->serialService->register(
            model: $model,
            serialNumber: $serialNumber,
            locationId: $locationId,
            batchId: $batchId,
            condition: $condition,
            unitCostMinor: $unitCostMinor,
            warrantyExpiresAt: $warrantyExpiresAt,
            userId: $userId,
        );
    }
}
