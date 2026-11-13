<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * CartManager decorator that adds inventory allocation functionality.
 *
 * Uses composition pattern to wrap any CartManagerInterface implementation,
 * enabling stacking with other decorators (e.g., CartManagerWithVouchers).
 */
final class CartManagerWithInventory implements CartManagerInterface
{
    private ?InventoryAllocationService $allocationService = null;

    public function __construct(
        private CartManagerInterface $manager
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->manager->{$method}(...$arguments);
    }

    /**
     * Create from existing CartManagerInterface.
     */
    public static function fromCartManager(CartManagerInterface $manager): self
    {
        if ($manager instanceof self) {
            return $manager;
        }

        return new self($manager);
    }

    /**
     * Get the underlying CartManager (unwraps all decorators if needed)
     */
    public function getBaseManager(): CartManagerInterface
    {
        if ($this->manager instanceof self) {
            return $this->manager->getBaseManager();
        }

        return $this->manager;
    }

    public function getCurrentCart(): Cart
    {
        return $this->manager->getCurrentCart();
    }

    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        return $this->manager->getCartInstance($name, $identifier);
    }

    public function instance(): string
    {
        return $this->manager->instance();
    }

    public function setInstance(string $name): static
    {
        $this->manager->setInstance($name);

        return $this;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->manager->setIdentifier($identifier);

        return $this;
    }

    public function forgetIdentifier(): static
    {
        $this->manager->forgetIdentifier();

        return $this;
    }

    public function forOwner(Model $owner): static
    {
        return new self($this->manager->forOwner($owner));
    }

    public function getOwnerType(): ?string
    {
        return $this->manager->getOwnerType();
    }

    public function getOwnerId(): string | int | null
    {
        return $this->manager->getOwnerId();
    }

    public function getById(string $uuid): ?Cart
    {
        return $this->manager->getById($uuid);
    }

    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        return $this->manager->swap($oldIdentifier, $newIdentifier, $instance);
    }

    /**
     * Set the allocation service.
     */
    public function setAllocationService(InventoryAllocationService $service): self
    {
        $this->allocationService = $service;

        return $this;
    }

    /**
     * Get the allocation service.
     */
    public function getAllocationService(): InventoryAllocationService
    {
        if ($this->allocationService === null) {
            $this->allocationService = app(InventoryAllocationService::class);
        }

        return $this->allocationService;
    }

    /**
     * Allocate inventory for all items in the current cart.
     *
     * Call this when entering checkout.
     *
     * Composite lines expand to their component models; the parent is never
     * allocated. Demand is merged per actual inventory model so a standalone
     * line and a component line for the same SKU reserve one combined
     * quantity. The whole batch runs in one outer transaction so a later
     * model failure rolls back earlier allocations instead of leaving them
     * reserved.
     *
     * @param  int  $ttlMinutes  Allocation expiry time
     * @return array<string, Collection<int, InventoryAllocation>> Results per source item ID
     */
    public function allocateAllInventory(int $ttlMinutes = 30): array
    {
        return DB::transaction(function () use ($ttlMinutes): array {
            $cart = $this->getCurrentCart();
            $results = [];
            $cartIdentifier = $this->getCartIdentifier();

            foreach (InventoryDemandResolver::forCart($cart->getItems()) as $entry) {
                $allocations = $this->getAllocationService()->allocate(
                    $entry['model'],
                    $entry['quantity'],
                    $cartIdentifier,
                    $ttlMinutes
                );

                foreach (array_keys($entry['sources']) as $itemId) {
                    $existing = $results[$itemId] ?? new Collection;
                    $results[$itemId] = $existing->merge($allocations);
                }
            }

            return $results;
        });
    }

    /**
     * Release all inventory allocations for the current cart.
     *
     * Call this when abandoning checkout or clearing cart.
     */
    public function releaseAllInventory(): int
    {
        return $this->getAllocationService()->releaseAllForCart(
            $this->getCartIdentifier()
        );
    }

    /**
     * Commit all allocations (convert to shipments after payment).
     *
     * @param  string|null  $orderId  Optional order reference
     * @return array<InventoryMovement>
     */
    public function commitInventory(?string $orderId = null): array
    {
        return $this->getAllocationService()->commit(
            $this->getCartIdentifier(),
            $orderId
        );
    }

    /**
     * Extend inventory allocations for the current cart.
     */
    public function extendInventoryAllocations(int $minutes): int
    {
        return $this->getAllocationService()->extendAllocations(
            $this->getCartIdentifier(),
            $minutes
        );
    }

    /**
     * Get all allocations for the current cart.
     *
     * @return Collection<int, InventoryAllocation>
     */
    public function getInventoryAllocations(): Collection
    {
        return $this->getAllocationService()->getAllocationsForCart(
            $this->getCartIdentifier()
        );
    }

    /**
     * Check if all items in cart have sufficient inventory.
     *
     * Composite lines are checked by their actual component models. Demand is
     * combined per inventory model across composite and standalone lines.
     *
     * @return array{available: bool, issues: array<string, array{name: string, requested: int, available: int, allocations: array<array{location_id: string, location_name: string, quantity: int}>}>}
     */
    public function validateInventory(): array
    {
        $cart = $this->getCurrentCart();
        $issues = [];
        $allAvailable = true;
        $cartIdentifier = $this->getCartIdentifier();

        foreach (InventoryDemandResolver::forCart($cart->getItems()) as $entry) {
            $model = $entry['model'];
            $totalAvailable = $this->getAllocationService()->getTotalAvailable($model);

            // Add back own allocations to availability check
            $ownAllocations = $this->getAllocationService()->getAllocations($model, $cartIdentifier);
            $ownAllocatedQty = $ownAllocations->sum('quantity');
            $effectiveAvailable = $totalAvailable + $ownAllocatedQty;

            if ($effectiveAvailable >= $entry['quantity']) {
                continue;
            }

            $allAvailable = false;

            // Get breakdown by location
            $locationBreakdown = [];
            foreach ($ownAllocations as $allocation) {
                $locationBreakdown[] = [
                    'location_id' => $allocation->location_id,
                    'location_name' => $allocation->location->name ?? 'Unknown',
                    'quantity' => $allocation->quantity,
                ];
            }

            foreach ($entry['sources'] as $itemId => $source) {
                $issues[$itemId] = [
                    'name' => $source['name'],
                    'requested' => $entry['quantity'],
                    'available' => $effectiveAvailable,
                    'allocations' => $locationBreakdown,
                ];
            }
        }

        return [
            'available' => $allAvailable,
            'issues' => $issues,
        ];
    }

    /**
     * Get the cart identifier for allocations.
     */
    private function getCartIdentifier(): string
    {
        $cart = $this->getCurrentCart();
        $cartId = $cart->getId();

        if ($cartId !== null) {
            return (string) $cartId;
        }

        return sprintf('%s_%s', $cart->getIdentifier(), $cart->instance());
    }
}
