<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use AIArmada\CommerceSupport\Contracts\Events\InventoryEventInterface;
use AIArmada\Inventory\Events\Concerns\HasInventoryEventData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InventoryReleased implements InventoryEventInterface
{
    use Dispatchable;
    use HasInventoryEventData;
    use SerializesModels;

    public function __construct(
        public Model $inventoryable,
        public int $quantity,
        public string $cartId
    ) {
        $this->initializeEventData();
    }

    /**
     * Get the event type identifier.
     */
    public function getEventType(): string
    {
        return 'inventory.released';
    }

    protected function resolveQuantity(): int
    {
        return $this->quantity;
    }
}
