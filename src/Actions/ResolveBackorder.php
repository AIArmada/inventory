<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Models\InventoryBackorder;
use AIArmada\Inventory\Services\Stock\BackorderService;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

final class ResolveBackorder
{
    use AsAction;

    public function __construct(
        private readonly BackorderService $backorderService,
    ) {}

    public function fulfill(InventoryBackorder $backorder, int $quantity): bool
    {
        return $this->backorderService->fulfill($backorder, $quantity);
    }

    public function cancel(InventoryBackorder $backorder, ?int $quantity = null, ?string $reason = null): bool
    {
        return $this->backorderService->cancel($backorder, $quantity, $reason);
    }

    /**
     * @return array{fulfilled: int, backorders_updated: int}
     */
    public function autoFulfill(Model $model, int $availableQuantity, ?string $locationId = null): array
    {
        return $this->backorderService->autoFulfill($model, $availableQuantity, $locationId);
    }
}
