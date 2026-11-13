<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Validates inventory availability when items are added to cart.
 *
 * Composite lines validate and allocate their actual component demand. Demand
 * is merged per inventory model across the whole cart, so a standalone line
 * and a component line for the same SKU never allocate twice.
 */
final class ValidateInventoryOnAdd
{
    public function __construct(
        private readonly InventoryAllocationService $allocationService,
    ) {}

    public function handle(ItemAdded $event): void
    {
        if (! config('inventory.cart.validate_on_add', false)) {
            return;
        }

        $item = $event->item;
        $cart = $event->cart;
        $cartId = (string) $cart->getId();

        $items = [];

        foreach ($cart->getItems() as $cartItem) {
            $items[(string) $cartItem->id] = $cartItem;
        }

        $items[(string) $item->id] = $item;

        $demand = InventoryDemandResolver::forCart($items);

        if ($demand === []) {
            return;
        }

        $deficits = $this->deficits($demand, $cartId);

        if ($deficits === []) {
            $this->allocateIfConfigured($demand, $cartId);
            $this->markAllocated($cart, $demand);

            return;
        }

        if (! (bool) config('inventory.cart.allow_backorder', false)) {
            $deficit = $deficits[0];

            throw new InsufficientInventoryException(
                sprintf(
                    'Insufficient inventory for %s. Requested: %d, Available: %d',
                    $deficit['name'],
                    $deficit['requested'],
                    $deficit['available']
                ),
                (string) $item->id,
                $deficit['requested'],
                $deficit['available']
            );
        }

        $this->assertBackorderLimit($deficits);
        $this->markBackorder($cart, $deficits);

        $this->allocateIfConfigured($demand, $cartId, allowPartial: true);
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     * @return list<array{name: string, requested: int, available: int, backorder: int, sources: array<string, array{name: string, quantity: int}>}>
     */
    private function deficits(array $demand, string $cartId): array
    {
        $deficits = [];

        foreach ($demand as $entry) {
            $available = $this->effectiveAvailable($entry['model'], $cartId);

            if ($available >= $entry['quantity']) {
                continue;
            }

            $deficits[] = [
                'name' => $this->sourceName($entry['sources']),
                'requested' => $entry['quantity'],
                'available' => $available,
                'backorder' => $entry['quantity'] - $available,
                'sources' => $entry['sources'],
            ];
        }

        return $deficits;
    }

    /**
     * @param  list<array{name: string, requested: int, available: int, backorder: int, sources: array<string, array{name: string, quantity: int}>}>  $deficits
     */
    private function assertBackorderLimit(array $deficits): void
    {
        $maxBackorder = config('inventory.cart.max_backorder_quantity');

        if ($maxBackorder === null) {
            return;
        }

        foreach ($deficits as $deficit) {
            if ($deficit['backorder'] <= (int) $maxBackorder) {
                continue;
            }

            throw new InsufficientInventoryException(
                sprintf(
                    'Backorder limit exceeded for %s. Max backorder: %d, Requested backorder: %d',
                    $deficit['name'],
                    (int) $maxBackorder,
                    $deficit['backorder']
                ),
                (string) array_key_first($deficit['sources']),
                $deficit['requested'],
                $deficit['available']
            );
        }
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     */
    private function allocateIfConfigured(array $demand, string $cartId, bool $allowPartial = false): void
    {
        if (! (bool) config('inventory.cart.auto_allocate_on_add', false)) {
            return;
        }

        $ttl = (int) config('inventory.allocation_ttl_minutes', 30);

        DB::transaction(function () use ($demand, $cartId, $ttl, $allowPartial): void {
            foreach ($demand as $entry) {
                $quantity = $allowPartial
                    ? min($entry['quantity'], $this->effectiveAvailable($entry['model'], $cartId))
                    : $entry['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

                $this->allocationService->allocate(
                    $entry['model'],
                    $quantity,
                    $cartId,
                    $ttl
                );
            }
        });
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     */
    private function markAllocated(object $cart, array $demand): void
    {
        if (! (bool) config('inventory.cart.auto_allocate_on_add', false)) {
            return;
        }

        $metadataKey = config('inventory.cart.allocation_metadata_key', 'inventory_allocated');

        foreach ($demand as $entry) {
            foreach ($entry['sources'] as $itemId => $source) {
                $cart->setMetadata($metadataKey . '.' . $itemId, [
                    'allocated' => true,
                    'quantity' => $source['quantity'],
                    'allocated_at' => CarbonImmutable::now()->toISOString(),
                ]);
            }
        }
    }

    /**
     * @param  list<array{name: string, requested: int, available: int, backorder: int, sources: array<string, array{name: string, quantity: int}>}>  $deficits
     */
    private function markBackorder(object $cart, array $deficits): void
    {
        $metadataKey = config('inventory.cart.backorder_metadata_key', 'is_backorder');

        foreach ($deficits as $deficit) {
            foreach ($deficit['sources'] as $itemId => $source) {
                $cart->setMetadata($metadataKey . '.' . $itemId, [
                    'is_backorder' => true,
                    'available_quantity' => $deficit['available'],
                    'backorder_quantity' => $deficit['backorder'],
                    'marked_at' => CarbonImmutable::now()->toISOString(),
                ]);
            }
        }
    }

    private function effectiveAvailable(Model $model, string $cartId): int
    {
        $available = $this->allocationService->getTotalAvailable($model);

        // The current cart may already hold allocations for this model. Add
        // them back because this listener re-evaluates the whole cart and
        // allocation replaces this cart's previous rows.
        return $available + $this->allocationService->getAllocations($model, $cartId)->sum('quantity');
    }

    /**
     * @param  array<string, array{name: string, quantity: int}>  $sources
     */
    private function sourceName(array $sources): string
    {
        $first = reset($sources);

        return is_array($first) && is_string($first['name'] ?? null)
            ? $first['name']
            : 'cart item';
    }
}
