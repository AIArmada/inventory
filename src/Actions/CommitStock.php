<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Lorisleiva\Actions\Concerns\AsAction;

final class CommitStock
{
    use AsAction;

    public function __construct(
        private readonly InventoryAllocationService $allocationService,
    ) {}

    /**
     * @return array<InventoryMovement>
     */
    public function handle(string $cartId, ?string $orderId = null): array
    {
        return $this->allocationService->commit($cartId, $orderId);
    }
}
