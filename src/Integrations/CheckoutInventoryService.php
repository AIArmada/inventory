<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Integrations;

use AIArmada\Inventory\Contracts\CheckoutInventoryServiceInterface;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Services\InventoryAllocationService;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

/**
 * Checkout integration service for inventory management.
 *
 * Bridges the checkout package's product ID-based approach with the
 * inventory package's polymorphic model-based operations.
 */
final class CheckoutInventoryService implements CheckoutInventoryServiceInterface
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryAllocationService $allocationService,
    ) {}

    /**
     * Get available stock for a product/variant.
     */
    public function getAvailableStock(string $productId, ?string $variantId = null): int
    {
        $model = $this->resolveInventoryModel($productId, $variantId);

        if ($model === null) {
            return 0;
        }

        return $this->inventoryService->getTotalAvailable($model);
    }

    /**
     * Reserve stock for a checkout session.
     *
     * @return array{id: string, expires_at: string}
     */
    public function reserve(
        string $productId,
        ?string $variantId,
        int $quantity,
        string $reference,
        int $ttl = 900,
    ): array {
        $model = $this->resolveInventoryModel($productId, $variantId);

        if ($model === null) {
            throw new InvalidArgumentException(
                "Cannot resolve inventory model for product {$productId}" . ($variantId ? " variant {$variantId}" : '')
            );
        }

        // Convert TTL from seconds to minutes for allocation service
        $ttlMinutes = max(1, (int) ceil($ttl / 60));

        try {
            $allocations = $this->allocationService->allocate(
                model: $model,
                quantity: $quantity,
                cartId: $reference,
                ttlMinutes: $ttlMinutes,
            );

            // Return the first allocation ID (there may be multiple if split across locations)
            // The reference (cart_id) can be used to manage all allocations together
            $firstAllocation = $allocations->first();
            $expiresAt = $firstAllocation?->expires_at ?? now()->addSeconds($ttl);

            return [
                'id' => $firstAllocation?->id ?? $reference,
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        } catch (InsufficientInventoryException $e) {
            throw new RuntimeException(
                "Insufficient inventory for product {$productId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Release a specific reservation by ID.
     */
    public function releaseReservation(string $reservationId): void
    {
        $allocation = $this->findAllocation($reservationId);

        if ($allocation !== null) {
            $this->allocationService->releaseAllocation($allocation);
        }
    }

    /**
     * Release all reservations for a reference (checkout session/cart ID).
     */
    public function releaseAllForReference(string $reference): int
    {
        return $this->allocationService->releaseAllForCart($reference);
    }

    /**
     * Commit a specific reservation.
     */
    public function commitReservation(string $reservationId): void
    {
        $allocation = $this->findAllocation($reservationId);

        if ($allocation === null) {
            return;
        }

        // Commit via the cart_id since that's how the allocation service works
        $this->allocationService->commit($allocation->cart_id);
    }

    /**
     * Commit all reservations for a reference.
     */
    public function commitAllForReference(string $reference, ?string $orderId = null): int
    {
        $movements = $this->allocationService->commit($reference, $orderId);

        return count($movements);
    }

    /**
     * Check availability for multiple items.
     *
     * @param  array<array{product_id: string, variant_id: string|null, quantity: int}>  $items
     * @return array{available: bool, items: array<string, array{available: bool, requested: int, stock: int}>}
     */
    public function checkBulkAvailability(array $items): array
    {
        $result = [
            'available' => true,
            'items' => [],
        ];

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $variantId = $item['variant_id'] ?? null;
            $quantity = $item['quantity'];

            $stock = $this->getAvailableStock($productId, $variantId);
            $itemAvailable = $stock >= $quantity;

            $key = $variantId !== null ? "{$productId}:{$variantId}" : $productId;
            $result['items'][$key] = [
                'available' => $itemAvailable,
                'requested' => $quantity,
                'stock' => $stock,
            ];

            if (! $itemAvailable) {
                $result['available'] = false;
            }
        }

        return $result;
    }

    /**
     * Extend reservations for a reference.
     */
    public function extendReservations(string $reference, int $ttl): int
    {
        // Convert TTL from seconds to minutes
        $minutes = max(1, (int) ceil($ttl / 60));

        return $this->allocationService->extendAllocations($reference, $minutes);
    }

    /**
     * Resolve the inventory model from product/variant IDs.
     *
     * Supports configurable model classes to work with any product/variant implementation.
     */
    private function resolveInventoryModel(string $productId, ?string $variantId = null): ?Model
    {
        // If variant ID is provided, try to resolve variant first
        if ($variantId !== null) {
            $variantModel = $this->resolveVariantModel($variantId);

            if ($variantModel !== null) {
                return $variantModel;
            }
        }

        // Fall back to product
        return $this->resolveProductModel($productId);
    }

    /**
     * Resolve a product model by ID.
     */
    private function resolveProductModel(string $productId): ?Model
    {
        $productClass = $this->getProductModelClass();

        if ($productClass === null || ! class_exists($productClass)) {
            return null;
        }

        return $productClass::query()->find($productId);
    }

    /**
     * Resolve a variant model by ID.
     */
    private function resolveVariantModel(string $variantId): ?Model
    {
        $variantClass = $this->getVariantModelClass();

        if ($variantClass === null || ! class_exists($variantClass)) {
            return null;
        }

        return $variantClass::query()->find($variantId);
    }

    /**
     * Get the configured product model class.
     */
    private function getProductModelClass(): ?string
    {
        // Check inventory config first, then checkout config, then default
        return config('inventory.models.product')
            ?? config('checkout.models.product')
            ?? config('products.models.product')
            ?? Product::class;
    }

    /**
     * Get the configured variant model class.
     */
    private function getVariantModelClass(): ?string
    {
        return config('inventory.models.variant')
            ?? config('checkout.models.variant')
            ?? config('products.models.variant')
            ?? Variant::class;
    }

    /**
     * Find an allocation by ID.
     */
    private function findAllocation(string $allocationId): ?InventoryAllocation
    {
        $query = InventoryAllocation::query()
            ->where('id', $allocationId)
            ->with('location');

        return InventoryOwnerScope::applyToQueryByLocationRelation($query)->first();
    }
}
