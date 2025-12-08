<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Akaunting\Money\Money;

final class ProductData extends ChipData
{
    public function __construct(
        public readonly string $name,
        public readonly string $quantity,
        public readonly Money $price,
        public readonly Money $discount,
        public readonly float $tax_percent,
        public readonly ?string $category,
    ) {}

    /**
     * Create a Product from array data (typically from CHIP API response).
     * Prices in the array are expected to be in cents (minor units).
     *
     * @param  array<string, mixed>|self  ...$payloads
     */
    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);
        $currency = $data['currency'] ?? 'MYR';

        return new self(
            name: $data['name'],
            quantity: (string) ($data['quantity'] ?? '1'),
            price: Money::{$currency}((int) $data['price']),
            discount: Money::{$currency}((int) ($data['discount'] ?? 0)),
            tax_percent: (float) ($data['tax_percent'] ?? 0.0),
            category: $data['category'] ?? null,
        );
    }

    /**
     * Create a Product with Money objects directly.
     */
    public static function make(
        string $name,
        Money $price,
        string|float|int $quantity = 1,
        ?Money $discount = null,
        float $taxPercent = 0.0,
        ?string $category = null,
    ): self {
        $currency = $price->getCurrency()->getCurrency();

        return new self(
            name: $name,
            quantity: (string) $quantity,
            price: $price,
            discount: $discount ?? Money::{$currency}(0),
            tax_percent: $taxPercent,
            category: $category,
        );
    }

    /**
     * Get the currency code for this product.
     */
    public function getCurrency(): string
    {
        return $this->price->getCurrency()->getCurrency();
    }

    /**
     * Get the price in cents (minor units) for API communication.
     */
    public function getPriceInCents(): int
    {
        return (int) $this->price->getAmount();
    }

    /**
     * Get the discount in cents (minor units) for API communication.
     */
    public function getDiscountInCents(): int
    {
        return (int) $this->discount->getAmount();
    }

    /**
     * Get the total price as Money (price - discount) × quantity.
     */
    public function getTotalPrice(): Money
    {
        $unitPrice = $this->price->subtract($this->discount);
        $quantity = (float) $this->quantity;
        $currency = $this->getCurrency();

        return Money::{$currency}((int) ($unitPrice->getAmount() * $quantity));
    }

    /**
     * Get the total price in cents for API communication.
     */
    public function getTotalPriceInCents(): int
    {
        return (int) $this->getTotalPrice()->getAmount();
    }

    /**
     * @deprecated Use getPriceInCents() or $this->price directly
     */
    public function getPriceInCurrency(): float
    {
        return $this->price->getAmount() / 100;
    }

    /**
     * @deprecated Use getDiscountInCents() or $this->discount directly
     */
    public function getDiscountInCurrency(): float
    {
        return $this->discount->getAmount() / 100;
    }

    /**
     * @deprecated Use getTotalPrice() directly
     */
    public function getTotalPriceInCurrency(): float
    {
        return $this->getTotalPrice()->getAmount() / 100;
    }

    /**
     * Convert to array for CHIP API (prices in cents).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->getPriceInCents(),
            'discount' => $this->getDiscountInCents(),
            'tax_percent' => $this->tax_percent,
            'category' => $this->category,
        ];
    }
}
