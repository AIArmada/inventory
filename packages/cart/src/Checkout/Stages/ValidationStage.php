<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout\Stages;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\Contracts\CheckoutStageInterface;
use AIArmada\Cart\Checkout\StageResult;
use AIArmada\Cart\Contracts\CartValidatorInterface;

/**
 * Validation stage for checkout pipeline.
 *
 * Validates the cart and all items before proceeding with checkout.
 * Runs all registered validators (inventory, voucher, custom, etc.).
 */
final class ValidationStage implements CheckoutStageInterface
{
    /**
     * @var array<CartValidatorInterface>
     */
    private array $validators = [];

    /**
     * @param  array<CartValidatorInterface>  $validators
     */
    public function __construct(array $validators = [])
    {
        $this->validators = $validators;
    }

    /**
     * Add a validator.
     */
    public function addValidator(CartValidatorInterface $validator): self
    {
        $this->validators[] = $validator;

        return $this;
    }

    public function getName(): string
    {
        return 'validation';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function shouldExecute(Cart $cart, array $context): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function execute(Cart $cart, array $context): StageResult
    {
        // Check if cart is empty
        if ($cart->isEmpty()) {
            return StageResult::failure('Cart is empty');
        }

        // Run all validators
        $allErrors = [];

        // Sort validators by priority
        $sortedValidators = $this->validators;
        usort($sortedValidators, fn ($a, $b) => $a->getPriority() <=> $b->getPriority());

        foreach ($sortedValidators as $validator) {
            $result = $validator->validateCart($cart);

            if (! $result->isValid) {
                $allErrors[$validator->getType()] = $result->message;
            }
        }

        if (! empty($allErrors)) {
            return StageResult::failure(
                'Cart validation failed',
                $allErrors
            );
        }

        return StageResult::success('Cart validated successfully', [
            'validated_at' => now()->toIso8601String(),
            'item_count' => $cart->countItems(),
            'total_quantity' => $cart->getTotalQuantity(),
        ]);
    }

    public function supportsRollback(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function rollback(Cart $cart, array $context): void
    {
        // Validation has no side effects to rollback
    }
}
