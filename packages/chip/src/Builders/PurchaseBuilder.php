<?php

declare(strict_types=1);

namespace AIArmada\Chip\Builders;

use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

final class PurchaseBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private ChipCollectService $service
    ) {}

    /**
     * Set the brand ID
     */
    public function brand(string $brandId): self
    {
        $this->data['brand_id'] = $brandId;

        return $this;
    }

    /**
     * Set purchase currency
     */
    public function currency(string $currency = 'MYR'): self
    {
        $this->data['purchase']['currency'] = $currency;

        return $this;
    }

    /**
     * Add a product using Money objects.
     *
     * This is the preferred way to add products as it ensures type-safe
     * currency handling across all commerce packages.
     *
     * @throws ChipValidationException If price is negative
     */
    public function addProductMoney(
        string $name,
        Money $price,
        string | float | int $quantity = 1,
        ?Money $discount = null,
        float $taxPercent = 0,
        ?string $category = null
    ): self {
        $priceAmount = (int) $price->getAmount();

        if ($priceAmount < 0) {
            throw new ChipValidationException('Product price cannot be negative', ['price' => $priceAmount]);
        }

        $currency = $price->getCurrency()->getCurrency();

        // Ensure currency is set on the purchase
        if (! isset($this->data['purchase']['currency'])) {
            $this->data['purchase']['currency'] = $currency;
        }

        $product = [
            'name' => $name,
            'price' => $priceAmount,
            'quantity' => (string) $quantity,
        ];

        if ($discount !== null && $discount->getAmount() > 0) {
            $product['discount'] = (int) $discount->getAmount();
        }

        if ($taxPercent > 0) {
            $product['tax_percent'] = $taxPercent;
        }

        if ($category !== null) {
            $product['category'] = $category;
        }

        $this->data['purchase']['products'][] = $product;

        return $this;
    }

    /**
     * Add a Product data object directly.
     */
    public function addProductObject(ProductData $product): self
    {
        // Ensure currency is set on the purchase
        if (! isset($this->data['purchase']['currency'])) {
            $this->data['purchase']['currency'] = $product->getCurrency();
        }

        $this->data['purchase']['products'][] = $product->toArray();

        return $this;
    }

    /**
     * Add a product from a LineItemInterface (universal contract).
     */
    public function addLineItem(LineItemInterface $item): self
    {
        return $this->addProductMoney(
            name: $item->getLineItemName(),
            price: $item->getLineItemPrice(),
            quantity: $item->getLineItemQuantity(),
            discount: $item->getLineItemDiscount(),
            taxPercent: $item->getLineItemTaxPercent(),
            category: $item->getLineItemCategory()
        );
    }

    /**
     * Add a product using price in cents (convenience method).
     *
     * This creates a Money object internally using the purchase currency.
     * Use addProductMoney() for explicit currency control.
     *
     * @throws ChipValidationException If price is negative
     */
    public function addProductCents(
        string $name,
        int $priceInCents,
        string | float | int $quantity = 1,
        int $discountInCents = 0,
        float $taxPercent = 0,
        ?string $category = null
    ): self {
        $currency = $this->data['purchase']['currency'] ?? config('chip.defaults.currency', 'MYR');

        return $this->addProductMoney(
            name: $name,
            price: Money::{$currency}($priceInCents),
            quantity: $quantity,
            discount: $discountInCents > 0 ? Money::{$currency}($discountInCents) : null,
            taxPercent: $taxPercent,
            category: $category
        );
    }

    /**
     * Build purchase from a CheckoutableInterface (Cart, Order, etc.).
     *
     * This is the recommended way to create a purchase from a cart or order
     * as it uses the universal contract for maximum portability.
     */
    public function fromCheckoutable(CheckoutableInterface $checkoutable): self
    {
        $this->currency($checkoutable->getCheckoutCurrency());
        $this->reference($checkoutable->getCheckoutReference());

        foreach ($checkoutable->getCheckoutLineItems() as $item) {
            $this->addLineItem($item);
        }

        if ($checkoutable->getCheckoutNotes() !== null) {
            $this->notes($checkoutable->getCheckoutNotes());
        }

        $metadata = $checkoutable->getCheckoutMetadata();
        if (! empty($metadata)) {
            $this->metadata($metadata);
        }

        return $this;
    }

    /**
     * Build purchase with customer from interfaces.
     */
    public function fromCheckoutWithCustomer(
        CheckoutableInterface $checkoutable,
        CustomerInterface $customer
    ): self {
        $this->fromCheckoutable($checkoutable);
        $this->fromCustomer($customer);

        return $this;
    }

    /**
     * Set customer details from a CustomerInterface.
     */
    public function fromCustomer(CustomerInterface $customer): self
    {
        $this->customer(
            email: $customer->getCustomerEmail(),
            fullName: $customer->getCustomerName(),
            phone: $customer->getCustomerPhone(),
            country: $customer->getCustomerCountry()
        );

        // Set billing address if available
        if ($customer->getBillingStreetAddress() !== null) {
            $this->billingAddress(
                streetAddress: $customer->getBillingStreetAddress(),
                city: $customer->getBillingCity() ?? '',
                zipCode: $customer->getBillingPostalCode() ?? '',
                state: $customer->getBillingState(),
                country: $customer->getBillingCountry()
            );
        }

        // Set shipping address if different from billing
        if ($customer->hasShippingAddress() && $customer->getShippingStreetAddress() !== null) {
            $this->shippingAddress(
                streetAddress: $customer->getShippingStreetAddress(),
                city: $customer->getShippingCity() ?? '',
                zipCode: $customer->getShippingPostalCode() ?? '',
                state: $customer->getShippingState(),
                country: $customer->getShippingCountry()
            );
        }

        // Use existing gateway customer ID if available
        if ($customer->getGatewayCustomerId() !== null) {
            $this->clientId($customer->getGatewayCustomerId());
        }

        return $this;
    }

    /**
     * Set customer email
     */
    public function email(string $email): self
    {
        $this->data['client']['email'] = $email;

        return $this;
    }

    /**
     * Set customer details
     */
    public function customer(
        string $email,
        ?string $fullName = null,
        ?string $phone = null,
        ?string $country = null
    ): self {
        $this->data['client']['email'] = $email;

        if ($fullName !== null) {
            $this->data['client']['full_name'] = $fullName;
        }

        if ($phone !== null) {
            $this->data['client']['phone'] = $phone;
        }

        if ($country !== null) {
            $this->data['client']['country'] = $country;
        }

        return $this;
    }

    /**
     * Set existing client ID
     */
    public function clientId(string $clientId): self
    {
        $this->data['client_id'] = $clientId;
        unset($this->data['client']);

        return $this;
    }

    /**
     * Set billing address
     */
    public function billingAddress(
        string $streetAddress,
        string $city,
        string $zipCode,
        ?string $state = null,
        ?string $country = null
    ): self {
        $this->data['client']['street_address'] = $streetAddress;
        $this->data['client']['city'] = $city;
        $this->data['client']['zip_code'] = $zipCode;

        if ($state !== null) {
            $this->data['client']['state'] = $state;
        }

        if ($country !== null) {
            $this->data['client']['country'] = $country;
        }

        return $this;
    }

    /**
     * Set shipping address
     */
    public function shippingAddress(
        string $streetAddress,
        string $city,
        string $zipCode,
        ?string $state = null,
        ?string $country = null
    ): self {
        $this->data['client']['shipping_street_address'] = $streetAddress;
        $this->data['client']['shipping_city'] = $city;
        $this->data['client']['shipping_zip_code'] = $zipCode;

        if ($state !== null) {
            $this->data['client']['shipping_state'] = $state;
        }

        if ($country !== null) {
            $this->data['client']['shipping_country'] = $country;
        }

        return $this;
    }

    /**
     * Set merchant reference
     */
    public function reference(string $reference): self
    {
        $this->data['reference'] = $reference;

        return $this;
    }

    /**
     * Set success redirect URL
     */
    public function successUrl(string $url): self
    {
        $this->data['success_redirect'] = $url;

        return $this;
    }

    /**
     * Set failure redirect URL
     */
    public function failureUrl(string $url): self
    {
        $this->data['failure_redirect'] = $url;

        return $this;
    }

    /**
     * Set cancel redirect URL
     */
    public function cancelUrl(string $url): self
    {
        $this->data['cancel_redirect'] = $url;

        return $this;
    }

    /**
     * Set all redirect URLs at once
     */
    public function redirects(string $successUrl, ?string $failureUrl = null, ?string $cancelUrl = null): self
    {
        $this->successUrl($successUrl);

        if ($failureUrl !== null) {
            $this->failureUrl($failureUrl);
        }

        if ($cancelUrl !== null) {
            $this->cancelUrl($cancelUrl);
        }

        return $this;
    }

    /**
     * Set webhook callback URL
     */
    public function webhook(string $url): self
    {
        $this->data['success_callback'] = $url;

        return $this;
    }

    /**
     * Enable email receipt
     */
    public function sendReceipt(bool $send = true): self
    {
        $this->data['send_receipt'] = $send;

        return $this;
    }

    /**
     * Enable pre-authorization (skip capture)
     */
    public function preAuthorize(bool $skipCapture = true): self
    {
        $this->data['skip_capture'] = $skipCapture;

        return $this;
    }

    /**
     * Force recurring token creation
     */
    public function forceRecurring(bool $force = true): self
    {
        $this->data['force_recurring'] = $force;

        return $this;
    }

    /**
     * Set due date
     */
    public function due(int $timestamp, bool $strict = false): self
    {
        $this->data['due'] = $timestamp;
        $this->data['due_strict'] = $strict;

        return $this;
    }

    /**
     * Add notes to the purchase
     */
    public function notes(string $notes): self
    {
        $this->data['purchase']['notes'] = $notes;

        return $this;
    }

    /**
     * Set a discount override for the entire purchase.
     *
     * This applies a cart-level discount that reduces the total amount charged.
     * The discount is applied after product prices are summed.
     *
     * @param  int  $amount  Discount amount in cents (e.g., 5000 for RM 50.00)
     */
    public function discount(int $amount): self
    {
        if ($amount > 0) {
            $this->data['purchase']['total_discount_override'] = $amount;
        }

        return $this;
    }

    /**
     * Set metadata for the purchase.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->data['purchase']['metadata'] = $metadata;

        return $this;
    }

    /**
     * Set an idempotency key to prevent duplicate purchases.
     *
     * If a purchase with this key already exists, the API will return
     * the existing purchase instead of creating a new one.
     *
     * Recommended format: "order-{order_id}" or use a UUID.
     */
    public function idempotencyKey(string $key): self
    {
        $this->data['idempotency_key'] = $key;

        return $this;
    }

    /**
     * Get the built data array (for inspection)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Create the purchase
     */
    public function create(): PurchaseData
    {
        // Use brand_id from config if not set
        if (! isset($this->data['brand_id'])) {
            $this->data['brand_id'] = config('chip.collect.brand_id');
        }

        return $this->service->createPurchase($this->data);
    }

    /**
     * Alias for create()
     */
    public function save(): PurchaseData
    {
        return $this->create();
    }
}
