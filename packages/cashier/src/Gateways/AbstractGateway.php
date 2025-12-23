<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Abstract base class for payment gateway implementations.
 *
 * This class provides common functionality shared across all gateway
 * implementations, such as money formatting and configuration handling.
 */
abstract class AbstractGateway implements GatewayContract
{
    /**
     * The gateway configuration.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Create a new gateway instance.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get the gateway name.
     */
    abstract public function name(): string;

    /**
     * Verify a webhook signature.
     *
     * @param  array<string, mixed>  $headers
     */
    abstract public function verifyWebhookSignature(string $payload, array $headers): bool;

    /**
     * Handle a webhook event.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    abstract public function handleWebhook(array $payload, array $headers = []): mixed;

    /**
     * Create a new subscription builder (alias).
     */
    abstract public function subscription(BillableContract $billable, string $type, string | array $prices = []): SubscriptionBuilderContract;

    /**
     * Get the customer adapter for this gateway.
     */
    abstract public function customer(BillableContract $billable): CustomerContract;

    /**
     * Create a new checkout session builder.
     */
    abstract public function checkout(BillableContract $billable): CheckoutBuilderContract;

    /**
     * Retrieve a checkout session.
     */
    abstract public function retrieveCheckout(string $sessionId): ?CheckoutContract;

    /**
     * Retrieve a subscription.
     */
    abstract public function retrieveSubscription(string $subscriptionId): ?SubscriptionContract;

    /**
     * Retrieve a payment.
     */
    abstract public function retrievePayment(string $paymentId): ?PaymentContract;

    /**
     * Retrieve an invoice.
     */
    abstract public function retrieveInvoice(string $invoiceId): ?InvoiceContract;

    /**
     * Get all subscriptions for a customer.
     *
     * @return Collection<int, SubscriptionContract>
     */
    abstract public function subscriptions(BillableContract $billable): Collection;

    /**
     * Get all invoices for a customer.
     *
     * @param  bool|array<string, mixed>  $parameters  Either includePending bool or parameters array
     * @return Collection<int, InvoiceContract>
     */
    abstract public function invoices(BillableContract $billable, bool | array $parameters = false): Collection;

    /**
     * Get all payment methods for a customer.
     *
     * @param  string|null  $type  Filter by payment method type (e.g., 'card')
     * @return Collection<int, PaymentMethodContract>
     */
    abstract public function paymentMethods(BillableContract $billable, ?string $type = null): Collection;

    /**
     * Find a specific payment method.
     */
    abstract public function findPaymentMethod(BillableContract $billable, string $paymentMethodId): ?PaymentMethodContract;

    /**
     * Get the default payment method for a customer.
     */
    abstract public function defaultPaymentMethod(BillableContract $billable): ?PaymentMethodContract;

    /**
     * Create a charge/payment.
     *
     * @param  int  $amount  Amount in cents
     * @param  array<string, mixed>  $options
     */
    abstract public function charge(BillableContract $billable, int $amount, ?string $paymentMethod = null, array $options = []): PaymentContract;

    /**
     * Create a setup intent for adding payment methods.
     *
     * @param  array<string, mixed>  $options
     */
    abstract public function createSetupIntent(BillableContract $billable, array $options = []): mixed;

    /**
     * Sync the customer's information to the gateway.
     *
     * @param  array<string, mixed>  $options
     */
    abstract public function syncCustomer(BillableContract $billable, array $options = []): CustomerContract;

    /**
     * Refund a payment.
     *
     * @param  int|null  $amount  Amount to refund in cents (null for full refund)
     */
    abstract public function refund(string $paymentId, ?int $amount = null): mixed;

    /**
     * Get the underlying gateway client.
     */
    abstract public function client(): mixed;

    /**
     * Get the gateway display name.
     */
    final public function displayName(): string
    {
        return ucfirst($this->name());
    }

    /**
     * Check if the gateway is available.
     */
    final public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Get the default currency.
     */
    final public function currency(): string
    {
        return mb_strtoupper($this->getConfig('currency', 'USD'));
    }

    /**
     * Get the currency locale for formatting.
     */
    final public function currencyLocale(): string
    {
        return $this->getConfig('currency_locale', $this->getLocale());
    }

    /**
     * Format an amount in cents to a displayable string.
     */
    final public function formatAmount(int $amount, ?string $currency = null): string
    {
        $currency = $currency ?? $this->currency();

        return Money::$currency($amount, true)->format($this->getLocale());
    }

    /**
     * Determine if the gateway is in test mode.
     */
    final public function isTestMode(): bool
    {
        return (bool) $this->getConfig('test_mode', false);
    }

    /**
     * Get the webhook secret.
     */
    final public function webhookSecret(): ?string
    {
        return $this->getConfig('webhook_secret');
    }

    /**
     * Find a billable by gateway customer ID.
     */
    final public function findBillable(string $gatewayId): ?BillableContract
    {
        $model = $this->billableModel();

        /** @var Builder<Model> $query */
        $query = $model::query();

        if (method_exists($model, 'ownerScopeConfig')) {
            /** @var OwnerScopeConfig $ownerScopeConfig */
            $ownerScopeConfig = $model::ownerScopeConfig();

            if ($ownerScopeConfig->enabled) {
                $owner = OwnerContext::resolve();

                if ($owner === null) {
                    return null;
                }

                $query = OwnerQuery::applyToEloquentBuilder(
                    $query->withoutGlobalScope(OwnerScope::class),
                    $owner,
                    false,
                    $ownerScopeConfig->ownerTypeColumn,
                    $ownerScopeConfig->ownerIdColumn,
                );
            }
        }

        $billable = $query->where($this->gatewayIdColumn(), $gatewayId)->first();

        if (! $billable instanceof BillableContract) {
            return null;
        }

        return $billable;
    }

    /**
     * Create a new subscription builder.
     */
    final public function newSubscription(BillableContract $billable, string $type, string | array $prices = []): SubscriptionBuilderContract
    {
        return $this->subscription($billable, $type, $prices);
    }

    /**
     * Find a payment by ID.
     */
    final public function findPayment(string $paymentId): ?PaymentContract
    {
        return $this->retrievePayment($paymentId);
    }

    /**
     * Find an invoice for a billable.
     */
    final public function findInvoice(BillableContract $billable, string $invoiceId): ?InvoiceContract
    {
        return $this->retrieveInvoice($invoiceId);
    }

    /**
     * Get a configuration value.
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Get the locale for formatting.
     */
    protected function getLocale(): string
    {
        return $this->getConfig('locale', config('app.locale', 'en_US'));
    }

    /**
     * Get the billable model class.
     *
     * @return class-string
     */
    protected function billableModel(): string
    {
        return $this->getConfig('model', config('cashier.models.billable', 'App\\Models\\User'));
    }

    /**
     * Get the gateway ID column name.
     */
    protected function gatewayIdColumn(): string
    {
        return $this->name() . '_id';
    }
}
