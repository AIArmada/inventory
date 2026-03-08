<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

/**
 * Interface for checkout package integration.
 *
 * Provides a simplified API for checkout operations that need to interact
 * with inventory. This bridges the checkout's product ID-based approach
 * with the inventory package's polymorphic model-based approach.
 */
interface CheckoutInventoryServiceInterface
{
    /**
     * Get available stock for a product/variant.
     *
     * @param  string  $productId  Product UUID
     * @param  string|null  $variantId  Variant UUID (optional)
     * @return int Available quantity (on_hand - reserved)
     */
    public function getAvailableStock(string $productId, ?string $variantId = null): int;

    /**
     * Reserve stock for a checkout session.
     *
     * @param  string  $productId  Product UUID
     * @param  string|null  $variantId  Variant UUID (optional)
     * @param  int  $quantity  Quantity to reserve
     * @param  string  $reference  Checkout session ID or cart ID
     * @param  int  $ttl  Reservation TTL in seconds
     * @return array{id: string, expires_at: string}
     */
    public function reserve(
        string $productId,
        ?string $variantId,
        int $quantity,
        string $reference,
        int $ttl = 900,
    ): array;

    /**
     * Release a specific reservation.
     *
     * @param  string  $reservationId  The allocation/reservation UUID
     */
    public function releaseReservation(string $reservationId): void;

    /**
     * Release all reservations for a checkout session/cart.
     *
     * @param  string  $reference  Checkout session ID or cart ID
     * @return int Total quantity released
     */
    public function releaseAllForReference(string $reference): int;

    /**
     * Commit a reservation (convert to actual stock deduction after payment).
     *
     * @param  string  $reservationId  The allocation/reservation UUID
     */
    public function commitReservation(string $reservationId): void;

    /**
     * Commit all reservations for a checkout session/cart.
     *
     * @param  string  $reference  Checkout session ID or cart ID
     * @param  string|null  $orderId  Optional order ID for tracking
     * @return int Number of reservations committed
     */
    public function commitAllForReference(string $reference, ?string $orderId = null): int;

    /**
     * Check availability for multiple items.
     *
     * @param  array<array{product_id: string, variant_id: string|null, quantity: int}>  $items
     * @return array{
     *   available: bool,
     *   items: array<string, array{available: bool, requested: int, stock: int}>
     * }
     */
    public function checkBulkAvailability(array $items): array;

    /**
     * Extend reservations for a checkout session/cart.
     *
     * @param  string  $reference  Checkout session ID or cart ID
     * @param  int  $ttl  Additional seconds to extend
     * @return int Number of reservations extended
     */
    public function extendReservations(string $reference, int $ttl): int;
}
