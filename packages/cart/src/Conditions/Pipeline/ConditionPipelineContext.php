<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Models\CartItem;

final class ConditionPipelineContext
{
    private ?int $initialAmount = null;

    public function __construct(
        private Cart $cart,
        private ?CartConditionCollection $conditions = null,
        ?int $initialAmount = null
    ) {
        $this->conditions ??= $cart->getConditions();
        $this->initialAmount = $initialAmount;
    }

    public static function fromCart(Cart $cart, ?int $initialAmount = null): self
    {
        return new self($cart, null, $initialAmount);
    }

    public function cart(): Cart
    {
        return $this->cart;
    }

    public function conditions(): CartConditionCollection
    {
        return $this->conditions ?? $this->cart->getConditions();
    }

    public function initialAmount(): int
    {
        if ($this->initialAmount !== null) {
            return $this->initialAmount;
        }

        $items = $this->cart->getItems();
        $this->initialAmount = (int) $items->sum(
            static fn (CartItem $item) => $item->getRawSubtotal()
        );

        return $this->initialAmount;
    }
}
