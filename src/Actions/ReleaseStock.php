<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

final class ReleaseStock
{
    use AsAction;

    public function __construct(
        private readonly InventoryAllocationService $allocationService,
    ) {}

    public function handle(Model $model, string $cartId): int
    {
        return $this->allocationService->release($model, $cartId);
    }

    public function releaseAllocation(InventoryAllocation $allocation): int
    {
        return $this->allocationService->releaseAllocation($allocation);
    }

    public function releaseAllForCart(string $cartId): int
    {
        return $this->allocationService->releaseAllForCart($cartId);
    }
}
