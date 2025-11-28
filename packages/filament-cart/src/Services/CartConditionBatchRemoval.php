<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\Cart\Cart;
use AIArmada\FilamentCart\Models\Cart as CartModel;
use AIArmada\Cart\Models\Condition;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Removes conditions from all active carts.
 *
 * This service provides batch operations to remove specific conditions
 * from all carts in the system. Useful when admin deactivates a global
 * condition and needs to remove it immediately from all active carts.
 */
final class CartConditionBatchRemoval
{
    public function __construct(
        private CartInstanceManager $cartInstances,
        private CartSyncManager $syncManager
    ) {}

    /**
     * Remove a specific condition from all active carts.
     *
     * This method:
     * 1. Finds all cart snapshots that have this condition
     * 2. Loads each cart
     * 3. Removes the condition (handles both static and dynamic)
     * 4. Saves the cart (which triggers sync to update snapshot)
     *
     * @param  string  $conditionName  The name of the condition to remove
     * @return array{
     *     success: bool,
     *     carts_processed: int,
     *     carts_updated: int,
     *     errors: array<string>
     * }
     */
    public function removeConditionFromAllCarts(string $conditionName): array
    {
        $cartsProcessed = 0;
        $cartsUpdated = 0;
        $errors = [];

        try {
            // Find all cart snapshots that might have this condition
            // We check both cart conditions and item conditions
            $affectedSnapshots = CartModel::query()
                ->where(function ($query) use ($conditionName): void {
                    // Check in normalized_data->conditions
                    $query->whereJsonContains('normalized_data->conditions', [['name' => $conditionName]])
                        // Or check in normalized_data->items->*->conditions
                        ->orWhereRaw("JSON_SEARCH(normalized_data, 'one', ?, null, '$.items[*].conditions[*].name') IS NOT NULL", [$conditionName]);
                })
                ->get();

            Log::info("Found {$affectedSnapshots->count()} cart snapshots with condition '{$conditionName}'");

            foreach ($affectedSnapshots as $snapshot) {
                $cartsProcessed++;

                try {
                    // Load the cart instance
                    $cart = $this->loadCartForSnapshot($snapshot);

                    if ($cart === null) {
                        $errors[] = "Could not load cart for snapshot ID: {$snapshot->id}";

                        continue;
                    }

                    $conditionRemoved = false;

                    // Try removing from regular conditions
                    if ($cart->getConditions()->has($conditionName)) {
                        $cart->removeCondition($conditionName);
                        $conditionRemoved = true;
                    }

                    // Try removing from dynamic conditions
                    if ($cart->getDynamicConditions()->has($conditionName)) {
                        $cart->removeDynamicCondition($conditionName);
                        $conditionRemoved = true;
                    }

                    // Try removing from item conditions
                    foreach ($cart->getItems() as $item) {
                        if ($item->getConditions()->has($conditionName)) {
                            $cart->removeItemCondition($item->getId(), $conditionName);
                            $conditionRemoved = true;
                        }
                    }

                    if ($conditionRemoved) {
                        // Sync the cart to update the database snapshot
                        $this->syncManager->sync($cart);
                        $cartsUpdated++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Error processing snapshot ID {$snapshot->id}: {$e->getMessage()}";
                    Log::error('Error removing condition from cart', [
                        'snapshot_id' => $snapshot->id,
                        'condition' => $conditionName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Batch condition removal completed', [
                'condition' => $conditionName,
                'processed' => $cartsProcessed,
                'updated' => $cartsUpdated,
                'errors' => count($errors),
            ]);

            return [
                'success' => true,
                'carts_processed' => $cartsProcessed,
                'carts_updated' => $cartsUpdated,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            Log::error('Batch condition removal failed', [
                'condition' => $conditionName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'carts_processed' => $cartsProcessed,
                'carts_updated' => $cartsUpdated,
                'errors' => [...$errors, "Fatal error: {$e->getMessage()}"],
            ];
        }
    }

    /**
     * Load a cart instance from a snapshot.
     */
    private function loadCartForSnapshot(CartModel $snapshot): ?Cart
    {
        try {
            // Set the cart instance based on snapshot
            $instance = $snapshot->instance ?? 'default';

            // Get identifier (user ID or session ID)
            $identifier = $snapshot->identifier;

            // Get the cart for this specific instance and identifier
            return $this->cartInstances->resolve($instance, $identifier);
        } catch (Exception $e) {
            Log::error('Failed to load cart from snapshot', [
                'snapshot_id' => $snapshot->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
