<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Contract for billable models (typically User).
 *
 * This contract defines the interface that any model using the Billable trait
 * must satisfy. It provides a unified API across all payment gateways.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection $subscriptions
 */
interface BillableContract
{
    /**
     * Get the gateway for this billable entity.
     */
    public function gateway(?string $gateway = null): GatewayContract;

    /**
     * Get the default gateway name for this billable.
     */
    public function defaultGateway(): string;

    /**
     * Get the gateway ID for a specific gateway.
     */
    public function gatewayId(?string $gateway = null): ?string;

    /**
     * Check if the billable has an ID for a specific gateway.
     */
    public function hasGatewayId(?string $gateway = null): bool;

    /**
     * Create the customer in the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function createAsCustomer(array $options = [], ?string $gateway = null): CustomerContract;

    /**
     * Create or get the customer in the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function createOrGetCustomer(array $options = [], ?string $gateway = null): CustomerContract;

    /**
     * Update the customer in the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateCustomer(array $options = [], ?string $gateway = null): CustomerContract;

    /**
     * Get the customer from the gateway.
     */
    public function asCustomer(?string $gateway = null): CustomerContract;

    /**
     * Sync customer details to the gateway.
     */
    public function syncCustomerDetails(?string $gateway = null): self;

    /**
     * Get the customer name.
     */
    public function customerName(): ?string;

    /**
     * Get the customer email.
     */
    public function customerEmail(): ?string;

    /**
     * Get the customer phone.
     */
    public function customerPhone(): ?string;

    /**
     * Get the customer address.
     *
     * @return array<string, mixed>
     */
    public function customerAddress(): array;

    /**
     * Get the preferred currency.
     */
    public function preferredCurrency(): string;

    /**
     * Get the preferred locale.
     */
    public function preferredLocale(): ?string;

    /**
     * Begin a new subscription.
     */
    public function newSubscription(string $type, string | array $prices = [], ?string $gateway = null): SubscriptionBuilderContract;

    /**
     * Determine if the billable is on trial for a subscription type.
     */
    public function onTrial(string $type = 'default', ?string $price = null): bool;

    /**
     * Determine if the trial has expired for a subscription type.
     */
    public function hasExpiredTrial(string $type = 'default', ?string $price = null): bool;

    /**
     * Determine if the billable is on a generic trial.
     */
    public function onGenericTrial(): bool;

    /**
     * Determine if the billable is subscribed to a subscription type.
     */
    public function subscribed(string $type = 'default', ?string $price = null): bool;

    /**
     * Get a subscription by type.
     */
    public function subscription(string $type = 'default'): ?SubscriptionContract;

    /**
     * Get all subscriptions.
     */
    public function subscriptions(): HasMany;

    /**
     * Determine if the billable has an incomplete payment.
     */
    public function hasIncompletePayment(string $type = 'default'): bool;

    /**
     * Determine if the billable is subscribed to a product.
     */
    public function subscribedToProduct(string | array $products, string $type = 'default'): bool;

    /**
     * Determine if the billable is subscribed to a price.
     */
    public function subscribedToPrice(string | array $prices, string $type = 'default'): bool;

    /**
     * Get the payment methods for the billable.
     */
    public function paymentMethods(?string $gateway = null): Collection;

    /**
     * Find a specific payment method.
     */
    public function findPaymentMethod(string $paymentMethodId, ?string $gateway = null): mixed;

    /**
     * Determine if the billable has a default payment method.
     */
    public function hasDefaultPaymentMethod(?string $gateway = null): bool;

    /**
     * Determine if the billable has any payment method.
     */
    public function hasPaymentMethod(?string $gateway = null): bool;

    /**
     * Get the default payment method.
     */
    public function defaultPaymentMethod(?string $gateway = null): mixed;

    /**
     * Update the default payment method.
     */
    public function updateDefaultPaymentMethod(string $paymentMethodId, ?string $gateway = null): self;

    /**
     * Delete a payment method.
     */
    public function deletePaymentMethod(string $paymentMethodId, ?string $gateway = null): void;

    /**
     * Delete all payment methods.
     */
    public function deletePaymentMethods(?string $gateway = null): void;

    /**
     * Charge the customer.
     *
     * @param  int  $amount  Amount in cents
     * @param  array<string, mixed>  $options
     */
    public function charge(int $amount, ?string $paymentMethod = null, array $options = [], ?string $gateway = null): mixed;

    /**
     * Create a checkout session.
     *
     * @param  array<string, mixed>  $sessionOptions
     * @param  array<string, mixed>  $customerOptions
     */
    public function checkout(string | array $items, array $sessionOptions = [], array $customerOptions = [], ?string $gateway = null): CheckoutContract;

    /**
     * Refund a payment.
     *
     * @param  int|null  $amount  Amount in cents (null for full refund)
     */
    public function refund(string $paymentId, ?int $amount = null, ?string $gateway = null): mixed;

    /**
     * Get all invoices.
     */
    public function invoices(bool $includePending = false, ?string $gateway = null): Collection;

    /**
     * Find an invoice.
     */
    public function findInvoice(string $invoiceId, ?string $gateway = null): ?InvoiceContract;

    /**
     * Get the upcoming invoice.
     */
    public function upcomingInvoice(?string $gateway = null): ?InvoiceContract;

    /**
     * Stripe: Get the Stripe customer ID.
     */
    public function stripeId(): ?string;

    /**
     * Stripe: Get the Stripe customer.
     */
    public function asStripeCustomer(): mixed;

    /**
     * Stripe: Create or get the Stripe customer.
     *
     * @param  array<string, mixed>  $options
     */
    public function createOrGetStripeCustomer(array $options = []): mixed;

    /**
     * Stripe: Update the Stripe customer.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateStripeCustomer(array $options = []): mixed;

    /**
     * Stripe: Sync the Stripe customer details.
     *
     * @param  array<string, mixed>  $options
     */
    public function syncStripeCustomerDetails(array $options = []): mixed;

    /**
     * Stripe: Create a setup intent.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSetupIntent(array $options = []): mixed;

    /**
     * Stripe: Get the Stripe billing portal URL.
     *
     * @param  array<string, mixed>  $options
     */
    public function billingPortalUrl(string $returnUrl, array $options = []): string;

    /**
     * CHIP: Create or get the CHIP customer.
     *
     * @param  array<string, mixed>  $options
     */
    public function createOrGetChipCustomer(array $options = []): mixed;

    /**
     * CHIP: Update the CHIP customer.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateChipCustomer(array $options = []): mixed;

    /**
     * CHIP: Sync the CHIP customer details.
     */
    public function syncChipCustomerDetails(): mixed;

    /**
     * CHIP: Create a setup purchase.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSetupPurchase(array $options = []): mixed;
}
