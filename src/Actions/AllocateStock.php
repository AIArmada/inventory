<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

final class AllocateStock
{
    use AsAction;

    public function __construct(
        private readonly InventoryAllocationService $allocationService,
    ) {}

    /**
     * @return Collection<int, InventoryAllocation>
     *
     * @throws InsufficientInventoryException
     */
    public function handle(
        Model $model,
        int $quantity,
        string $cartId,
        int $ttlMinutes = 30,
    ): Collection {
        return $this->allocationService->allocate(
            model: $model,
            quantity: $quantity,
            cartId: $cartId,
            ttlMinutes: $ttlMinutes,
        );
    }
}
