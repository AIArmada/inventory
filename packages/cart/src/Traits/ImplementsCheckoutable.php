<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

/**
 * Implements CheckoutableInterface for Cart.
 *
 * This trait provides the methods required for payment gateway integration,
 * allowing the Cart to be passed directly to any PaymentGatewayInterface.
 */
trait ImplementsCheckoutable
{
    /**
     * Get line items for checkout/payment.
     *
     * @return iterable<LineItemInterface>
     */
    public function getCheckoutLineItems(): iterable
    {
        return $this->getItems()->all();
    }

    /**
     * Get the subtotal before discounts and taxes.
     */
    public function getCheckoutSubtotal(): Money
    {
        return $this->getSubtotalWithoutConditions();
    }

    /**
     * Get the total discount amount applied.
     */
    public function getCheckoutDiscount(): Money
    {
        $currency = config('cart.money.default_currency', 'USD');

        // Calculate total discount from conditions
        $discountConditions = $this->getConditionsByType('discount');
        $totalDiscount = 0;

        foreach ($discountConditions as $condition) {
            $totalDiscount += abs($condition->getCalculatedValue($this->getRawSubtotalWithoutConditions()));
        }

        // Also add item-level discounts
        foreach ($this->getItems() as $item) {
            $totalDiscount += $item->getDiscountAmount()->getAmount();
        }

        return Money::{$currency}((int) $totalDiscount);
    }

    /**
     * Get the total tax amount applied.
     */
    public function getCheckoutTax(): Money
    {
        $currency = config('cart.money.default_currency', 'USD');

        // Calculate total tax from conditions
        $taxConditions = $this->getConditionsByType('tax');
        $totalTax = 0;

        foreach ($taxConditions as $condition) {
            $totalTax += $condition->getCalculatedValue($this->getRawSubtotalWithoutConditions());
        }

        return Money::{$currency}((int) $totalTax);
    }

    /**
     * Get the shipping amount.
     */
    public function getCheckoutShipping(): Money
    {
        $currency = config('cart.money.default_currency', 'USD');

        $shippingValue = $this->getShippingValue();

        return Money::{$currency}($shippingValue !== null ? (int) ($shippingValue * 100) : 0);
    }

    /**
     * Get the final total to be charged.
     */
    public function getCheckoutTotal(): Money
    {
        return $this->getTotal();
    }

    /**
     * Get the currency code for checkout.
     */
    public function getCheckoutCurrency(): string
    {
        return config('cart.money.default_currency', 'USD');
    }

    /**
     * Get a unique reference for this checkout.
     */
    public function getCheckoutReference(): string
    {
        // Use cart ID if available, otherwise generate from identifier and instance
        $cartId = $this->getId();

        if ($cartId !== null) {
            return $cartId;
        }

        return sprintf(
            'cart_%s_%s_%s',
            $this->getIdentifier(),
            $this->instance(),
            $this->getVersion() ?? time()
        );
    }

    /**
     * Get optional notes for the checkout.
     */
    public function getCheckoutNotes(): ?string
    {
        return $this->getMetadata('notes') ?? $this->getMetadata('checkout_notes');
    }

    /**
     * Get additional metadata for checkout.
     *
     * @return array<string, mixed>
     */
    public function getCheckoutMetadata(): array
    {
        // Collect all metadata from storage
        $metadata = [
            'cart_identifier' => $this->getIdentifier(),
            'cart_instance' => $this->instance(),
            'cart_version' => $this->getVersion(),
            'item_count' => $this->countItems(),
            'total_quantity' => $this->getTotalQuantity(),
        ];

        // Add shipping method if set
        $shippingMethod = $this->getShippingMethod();
        if ($shippingMethod !== null) {
            $metadata['shipping_method'] = $shippingMethod;
        }

        // Add condition summaries
        $conditions = $this->getConditions();
        if ($conditions->isNotEmpty()) {
            $metadata['conditions'] = $conditions->map(fn($c) => [
                'name' => $c->getName(),
                'type' => $c->getType(),
                'value' => $c->getValue(),
            ])->values()->toArray();
        }

        return $metadata;
    }
}
