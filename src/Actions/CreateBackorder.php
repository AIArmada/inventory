<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Enums\BackorderPriority;
use AIArmada\Inventory\Models\InventoryBackorder;
use AIArmada\Inventory\Services\Stock\BackorderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateBackorder
{
    use AsAction;

    public function __construct(
        private readonly BackorderService $backorderService,
    ) {}

    public function handle(
        Model $model,
        int $quantity,
        ?string $locationId = null,
        ?string $orderId = null,
        ?string $customerId = null,
        BackorderPriority $priority = BackorderPriority::Normal,
        ?Carbon $promisedAt = null,
        ?string $notes = null,
    ): InventoryBackorder {
        return $this->backorderService->create(
            model: $model,
            quantity: $quantity,
            locationId: $locationId,
            orderId: $orderId,
            customerId: $customerId,
            priority: $priority,
            promisedAt: $promisedAt,
            notes: $notes,
        );
    }
}
