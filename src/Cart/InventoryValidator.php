<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartValidationResult;
use AIArmada\Cart\Contracts\CartValidatorInterface;
use AIArmada\Cart\Models\CartItem;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;

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
     *
     * Composite lines validate their actual component demand, and demand for
     * the same inventory model is combined across composite and standalone
     * lines before comparing it with available stock.
     */
    public function validateCart(Cart $cart): CartValidationResult
    {
        $errors = [];
        $metadata = [];

        if ($this->allowsBackorder()) {
            return CartValidationResult::valid();
        }

        foreach ($this->demandIssues($cart) as $itemId => $issue) {
            $errors[$itemId] = $issue['message'];
            $metadata[$itemId] = $issue['metadata'];
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
     *
     * The item's own demand is merged with every other cart line that targets
     * the same actual inventory model before the availability check.
     */
    public function validateItem(CartItem $item, Cart $cart): CartValidationResult
    {
        if ($this->allowsBackorder()) {
            return CartValidationResult::valid();
        }

        $issues = $this->demandIssues($cart, $item);
        $issue = $issues[$item->id] ?? null;

        if ($issue === null) {
            return CartValidationResult::valid();
        }

        return CartValidationResult::invalid(
            message: $issue['message'],
            metadata: $issue['metadata']
        );
    }

    /**
     * @return array<string, array{message: string, metadata: array<string, mixed>}>
     */
    private function demandIssues(Cart $cart, ?CartItem $include = null): array
    {
        $items = [];

        foreach ($cart->getItems() as $cartItem) {
            $items[(string) $cartItem->id] = $cartItem;
        }

        if ($include !== null) {
            $items[(string) $include->id] = $include;
        }

        $issues = [];

        foreach (InventoryDemandResolver::forCart($items) as $entry) {
            $availableQuantity = $this->allocationService->getTotalAvailable($entry['model']);
            $requestedQuantity = $entry['quantity'];

            if ($availableQuantity >= $requestedQuantity) {
                continue;
            }

            foreach ($entry['sources'] as $itemId => $source) {
                $issues[$itemId] = [
                    'message' => "Insufficient inventory for {$source['name']}. Available: {$availableQuantity}, Requested: {$requestedQuantity}",
                    'metadata' => [
                        'item_id' => $itemId,
                        'item_name' => $source['name'],
                        'available_quantity' => $availableQuantity,
                        'requested_quantity' => $requestedQuantity,
                        'shortfall' => $requestedQuantity - $availableQuantity,
                    ],
                ];
            }
        }

        return $issues;
    }

    private function allowsBackorder(): bool
    {
        return (bool) config('inventory.cart.allow_backorder', false);
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
