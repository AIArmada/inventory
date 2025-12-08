<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Akaunting\Money\Money;

final class PurchaseDetailsData extends ChipData
{
    public function __construct(
        public readonly string $currency,
        /** @var array<int, ProductData> */
        public readonly array $products,
        public readonly Money $total,
        public readonly string $language,
        public readonly ?string $notes,
        public readonly Money $debt,
        public readonly ?Money $subtotal_override,
        public readonly ?Money $total_tax_override,
        public readonly ?Money $total_discount_override,
        public readonly ?Money $total_override,
        /** @var array<string, mixed> */
        public readonly array $request_client_details,
        public readonly string $timezone,
        public readonly bool $due_strict,
        public readonly ?string $email_message,
        /** @var array<string, mixed>|null */
        public readonly ?array $metadata,
    ) {}

    /**
     * Create PurchaseDetails from array data (typically from CHIP API response).
     * Amounts in the array are expected to be in cents (minor units).
     *
     * @param  array<string, mixed>|self  ...$payloads
     */
    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);
        $currency = $data['currency'] ?? 'MYR';

        return new self(
            currency: $currency,
            products: isset($data['products'])
                ? array_map(fn ($product) => ProductData::from($product + ['currency' => $currency]), $data['products'])
                : [],
            total: Money::{$currency}($data['total'] ?? 0),
            language: $data['language'] ?? 'en',
            notes: $data['notes'] ?? null,
            debt: Money::{$currency}($data['debt'] ?? 0),
            subtotal_override: isset($data['subtotal_override']) ? Money::{$currency}($data['subtotal_override']) : null,
            total_tax_override: isset($data['total_tax_override']) ? Money::{$currency}($data['total_tax_override']) : null,
            total_discount_override: isset($data['total_discount_override']) ? Money::{$currency}($data['total_discount_override']) : null,
            total_override: isset($data['total_override']) ? Money::{$currency}($data['total_override']) : null,
            request_client_details: is_array($data['request_client_details'] ?? null) ? $data['request_client_details'] : [],
            timezone: $data['timezone'] ?? 'Asia/Kuala_Lumpur',
            due_strict: $data['due_strict'] ?? false,
            email_message: $data['email_message'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Get the total in cents for API communication.
     */
    public function getTotalInCents(): int
    {
        return (int) $this->total->getAmount();
    }

    /**
     * Get the calculated subtotal from products as Money.
     */
    public function getSubtotal(): Money
    {
        $subtotalCents = array_reduce($this->products, function ($carry, ProductData $product) {
            return $carry + $product->getPriceInCents() * (float) $product->quantity;
        }, 0);

        return Money::{$this->currency}((int) $subtotalCents);
    }

    /**
     * Get the subtotal in cents for API communication.
     */
    public function getSubtotalInCents(): int
    {
        return (int) $this->getSubtotal()->getAmount();
    }

    /**
     * @deprecated Use getTotalInCents() or $this->total directly
     */
    public function getTotalInCurrency(): float
    {
        return $this->total->getAmount() / 100;
    }

    /**
     * @deprecated Use getSubtotalInCents() or getSubtotal() directly
     */
    public function getSubtotalInCurrency(): float
    {
        return $this->getSubtotal()->getAmount() / 100;
    }

    /**
     * Convert to array for CHIP API (amounts in cents).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'products' => array_map(fn ($product) => $product->toArray(), $this->products),
            'total' => $this->getTotalInCents(),
            'language' => $this->language,
            'notes' => $this->notes,
            'debt' => (int) $this->debt->getAmount(),
            'subtotal_override' => $this->subtotal_override ? (int) $this->subtotal_override->getAmount() : null,
            'total_tax_override' => $this->total_tax_override ? (int) $this->total_tax_override->getAmount() : null,
            'total_discount_override' => $this->total_discount_override ? (int) $this->total_discount_override->getAmount() : null,
            'total_override' => $this->total_override ? (int) $this->total_override->getAmount() : null,
            'request_client_details' => $this->request_client_details,
            'timezone' => $this->timezone,
            'due_strict' => $this->due_strict,
            'email_message' => $this->email_message,
        ];
    }
}
