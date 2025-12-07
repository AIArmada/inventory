<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Services;

use AIArmada\Cart\Cart;

/**
 * Evaluates free shipping eligibility.
 */
class FreeShippingEvaluator
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected readonly array $config = []
    ) {}

    /**
     * Evaluate free shipping for a cart.
     */
    public function evaluate(Cart $cart): ?FreeShippingResult
    {
        $enabled = $this->config['enabled'] ?? false;

        if (! $enabled) {
            return null;
        }

        $threshold = $this->config['threshold'] ?? null;

        if ($threshold === null) {
            return null;
        }

        $cartTotal = $cart->subtotal()->getAmount();

        // Check if cart meets threshold
        if ($cartTotal >= $threshold) {
            return new FreeShippingResult(
                applies: true,
                message: 'Free shipping applied!',
            );
        }

        // Calculate remaining amount
        $remaining = $threshold - $cartTotal;

        return new FreeShippingResult(
            applies: false,
            nearThreshold: true,
            remainingAmount: $remaining,
            message: $this->formatRemainingMessage($remaining),
        );
    }

    /**
     * Format the remaining amount message.
     */
    protected function formatRemainingMessage(int $remaining): string
    {
        $formatted = number_format($remaining / 100, 2);
        $currency = $this->config['currency'] ?? 'RM';

        return "Add {$currency}{$formatted} more for free shipping!";
    }
}
