<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryAllocationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

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

    public function session(?string $sessionKey = null): StorageInterface
    {
        return $this->manager->session($sessionKey);
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
     * @param  int  $ttlMinutes  Allocation expiry time
     * @return array<string, Collection<int, InventoryAllocation>> Results per item ID
     */
    public function allocateAllInventory(int $ttlMinutes = 30): array
    {
        $cart = $this->getCurrentCart();
        $results = [];
        $cartIdentifier = $this->getCartIdentifier();

        foreach ($cart->getItems() as $item) {
            $model = $item->getAssociatedModel();

            if (! $model instanceof Model) {
                continue;
            }

            try {
                $allocations = $this->getAllocationService()->allocate(
                    $model,
                    $item->quantity,
                    $cartIdentifier,
                    $ttlMinutes
                );

                $results[$item->id] = $allocations;
            } catch (InvalidArgumentException $e) {
                // Rollback any successful allocations on failure
                $this->releaseAllInventory();

                throw $e;
            }
        }

        return $results;
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
     * @return array{available: bool, issues: array<string, array{name: string, requested: int, available: int, allocations: array<array{location_id: string, location_name: string, quantity: int}>}>}
     */
    public function validateInventory(): array
    {
        $cart = $this->getCurrentCart();
        $issues = [];
        $allAvailable = true;
        $cartIdentifier = $this->getCartIdentifier();

        foreach ($cart->getItems() as $item) {
            $model = $item->getAssociatedModel();

            if (! $model instanceof Model) {
                continue;
            }

            $totalAvailable = $this->getAllocationService()->getTotalAvailable($model);

            // Add back own allocations to availability check
            $ownAllocations = $this->getAllocationService()->getAllocations($model, $cartIdentifier);
            $ownAllocatedQty = $ownAllocations->sum('quantity');
            $effectiveAvailable = $totalAvailable + $ownAllocatedQty;

            if ($effectiveAvailable < $item->quantity) {
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

                $issues[$item->id] = [
                    'name' => $item->name,
                    'requested' => $item->quantity,
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
