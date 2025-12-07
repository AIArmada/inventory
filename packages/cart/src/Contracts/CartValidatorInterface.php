<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;

/**
 * Interface for cart validation providers.
 *
 * Implemented by packages that need to validate cart contents
 * at critical moments (checkout, before payment, etc.).
 */
interface CartValidatorInterface
{
    /**
     * Validate the entire cart.
     */
    public function validateCart(Cart $cart): CartValidationResult;

    /**
     * Validate a specific cart item.
     */
    public function validateItem(CartItem $item, Cart $cart): CartValidationResult;

    /**
     * Get the validator type identifier.
     */
    public function getType(): string;

    /**
     * Get the priority for validation order.
     * Lower numbers run first.
     */
    public function getPriority(): int;
}
