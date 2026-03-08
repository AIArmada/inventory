<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartValidationResult;
use AIArmada\Cart\Contracts\CartValidatorInterface;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Inventory\Services\InventoryAllocationService;
use Illuminate\Database\Eloquent\Model;

/**
 * Validates cart items against inventory availability.
 *
 * Used during checkout to ensure all items are in stock
 * and can be fulfilled.
 */
final readonly class InventoryValidator implements CartValidatorInterface
{
    private const string VALIDATOR_TYPE = 'inventory';

    private const int PRIORITY = 10;

    public function __construct(
        private InventoryAllocationService $allocationService
    ) {}

    /**
     * Validate the entire cart for inventory availability.
     */
    public function validateCart(Cart $cart): CartValidationResult
    {
        $errors = [];
        $metadata = [];

        foreach ($cart->getItems() as $item) {
            $itemResult = $this->validateItem($item, $cart);

            if (! $itemResult->isValid) {
                $errors[$item->id] = $itemResult->message ?? 'Insufficient inventory';
                $metadata[$item->id] = $itemResult->metadata;
            }
        }

        if (count($errors) > 0) {
            $count = count($errors);

            return CartValidationResult::invalid(
                message: "{$count} item(s) have insufficient inventory",
                errors: $errors,
                metadata: ['items' => $metadata]
            );
        }

        return CartValidationResult::valid();
    }

    /**
     * Validate a specific cart item for inventory availability.
     */
    public function validateItem(CartItem $item, Cart $cart): CartValidationResult
    {
        $model = $item->getAssociatedModel();

        if (! $model instanceof Model) {
            // Items without models are not inventory-tracked
            return CartValidationResult::valid();
        }

        $requestedQuantity = $item->quantity;
        $availableQuantity = $this->allocationService->getTotalAvailable($model);

        if ($availableQuantity >= $requestedQuantity) {
            return CartValidationResult::valid();
        }

        $allowBackorder = config('inventory.cart.allow_backorder', false);

        if ($allowBackorder) {
            return CartValidationResult::valid();
        }

        return CartValidationResult::invalid(
            message: "Insufficient inventory for {$item->name}. Available: {$availableQuantity}, Requested: {$requestedQuantity}",
            metadata: [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'available_quantity' => $availableQuantity,
                'requested_quantity' => $requestedQuantity,
                'shortfall' => $requestedQuantity - $availableQuantity,
            ]
        );
    }

    /**
     * Get the validator type identifier.
     */
    public function getType(): string
    {
        return self::VALIDATOR_TYPE;
    }

    /**
     * Get the priority for validation order.
     * Inventory validation runs early (priority 10).
     */
    public function getPriority(): int
    {
        return self::PRIORITY;
    }
}
