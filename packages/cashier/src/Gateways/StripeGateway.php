<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\CustomerContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\Contracts\PaymentMethodContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Gateways\Stripe\StripeCheckoutBuilder;
use AIArmada\Cashier\Gateways\Stripe\StripeCustomer;
use AIArmada\Cashier\Gateways\Stripe\StripeInvoice;
use AIArmada\Cashier\Gateways\Stripe\StripePayment;
use AIArmada\Cashier\Gateways\Stripe\StripePaymentMethod;
use AIArmada\Cashier\Gateways\Stripe\StripeSubscription;
use AIArmada\Cashier\Gateways\Stripe\StripeSubscriptionBuilder;
use Illuminate\Support\Collection;
use Laravel\Cashier\Cashier;
use SensitiveParameter;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;

/**
 * Stripe payment gateway implementation.
 *
 * This gateway wraps Laravel Cashier for Stripe functionality,
 * providing a unified interface compatible with the multi-gateway system.
 */
class StripeGateway extends AbstractGateway
{
    /**
     * The Stripe client instance.
     */
    protected ?StripeClient $stripeClient = null;

    /**
     * Get the gateway name.
     */
    public function name(): string
    {
        return 'stripe';
    }

    /**
     * Get the Stripe client.
     */
    public function client(): StripeClient
    {
        if ($this->stripeClient === null) {
            $this->stripeClient = Cashier::stripe();
        }

        return $this->stripeClient;
    }

    /**
     * Get the customer adapter for this gateway.
     */
    public function customer(BillableContract $billable): CustomerContract
    {
        $stripeCustomer = $billable->asStripeCustomer();

        return new StripeCustomer($stripeCustomer, $billable);
    }

    /**
     * Create or get a customer on Stripe.
     *
     * @param  array<string, mixed>  $options
     */
    public function createCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        $stripeCustomer = $billable->createOrGetStripeCustomer($options);

        return new StripeCustomer($stripeCustomer, $billable);
    }

    /**
     * Update customer on Stripe.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        $stripeCustomer = $billable->updateStripeCustomer($options);

        return new StripeCustomer($stripeCustomer, $billable);
    }

    /**
     * Sync customer information to Stripe.
     *
     * @param  array<string, mixed>  $options
     */
    public function syncCustomer(BillableContract $billable, array $options = []): CustomerContract
    {
        $stripeCustomer = $billable->syncStripeCustomerDetails($options);

        return new StripeCustomer($stripeCustomer, $billable);
    }

    /**
     * Create a one-time charge.
     *
     * @param  array<string, mixed>  $options
     */
    public function charge(BillableContract $billable, int $amount, #[SensitiveParameter] ?string $paymentMethod = null, array $options = []): PaymentContract
    {
        $payment = $billable->charge($amount, $paymentMethod, $options);

        return new StripePayment($payment);
    }

    /**
     * Refund a payment.
     *
     * @param  int|null  $amount  Amount to refund in cents (null for full refund)
     */
    public function refund(string $paymentId, ?int $amount = null): mixed
    {
        $refund = $this->client()->refunds->create([
            'payment_intent' => $paymentId,
            'amount' => $amount,
        ]);

        // Retrieve the updated payment
        $payment = $this->client()->paymentIntents->retrieve($paymentId);

        return new StripePayment(new \Laravel\Cashier\Payment($payment));
    }

    /**
     * Create a new subscription builder.
     */
    public function subscription(BillableContract $billable, string $type, string | array $prices = []): SubscriptionBuilderContract
    {
        return new StripeSubscriptionBuilder($billable, $type, $prices);
    }

    /**
     * Create a new checkout session builder.
     */
    public function checkout(BillableContract $billable): CheckoutBuilderContract
    {
        return new StripeCheckoutBuilder($this, $billable);
    }

    /**
     * Retrieve a checkout session.
     */
    public function retrieveCheckout(string $sessionId): ?CheckoutContract
    {
        try {
            $session = $this->client()->checkout->sessions->retrieve($sessionId);

            return new Stripe\StripeCheckout($session);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Retrieve a subscription.
     */
    public function retrieveSubscription(string $subscriptionId): ?SubscriptionContract
    {
        try {
            $subscription = $this->client()->subscriptions->retrieve($subscriptionId);

            return new StripeSubscription($subscription);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Retrieve a payment.
     */
    public function retrievePayment(string $paymentId): ?PaymentContract
    {
        try {
            $paymentIntent = $this->client()->paymentIntents->retrieve($paymentId);

            return new StripePayment(new \Laravel\Cashier\Payment($paymentIntent));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Retrieve an invoice.
     */
    public function retrieveInvoice(string $invoiceId): ?InvoiceContract
    {
        try {
            $invoice = $this->client()->invoices->retrieve($invoiceId);

            return new StripeInvoice($invoice);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get all subscriptions for a customer.
     *
     * @return Collection<int, SubscriptionContract>
     */
    public function subscriptions(BillableContract $billable): Collection
    {
        $subscriptions = $billable->subscriptions()
            ->get()
            ->map(fn ($subscription) => new StripeSubscription($subscription))
            ->values();

        /** @var Collection<int, SubscriptionContract> $subscriptions */
        return $subscriptions;
    }

    /**
     * Get all invoices for a customer.
     *
     * @param  bool|array<string, mixed>  $parameters  Either includePending bool or parameters array
     * @return Collection<int, InvoiceContract>
     */
    public function invoices(BillableContract $billable, bool | array $parameters = false): Collection
    {
        $includePending = is_bool($parameters) ? $parameters : ($parameters['include_pending'] ?? false);

        $invoices = $billable->invoices($includePending)
            ->map(fn ($invoice) => new StripeInvoice($invoice->asStripeInvoice()))
            ->values();

        /** @var Collection<int, InvoiceContract> $invoices */
        return $invoices;
    }

    /**
     * Get all payment methods for a customer.
     *
     * @param  string|null  $type  Filter by payment method type (e.g., 'card')
     * @return Collection<int, PaymentMethodContract>
     */
    public function paymentMethods(BillableContract $billable, ?string $type = null): Collection
    {
        $paymentMethods = $billable->paymentMethods($type)
            ->map(fn ($paymentMethod) => new StripePaymentMethod($paymentMethod, $billable))
            ->values();

        /** @var Collection<int, PaymentMethodContract> $paymentMethods */
        return $paymentMethods;
    }

    /**
     * Find a specific payment method.
     */
    public function findPaymentMethod(BillableContract $billable, string $paymentMethodId): ?PaymentMethodContract
    {
        $paymentMethod = $billable->findPaymentMethod($paymentMethodId);

        if (! $paymentMethod) {
            return null;
        }

        return new StripePaymentMethod($paymentMethod, $billable);
    }

    /**
     * Get the default payment method for a customer.
     */
    public function defaultPaymentMethod(BillableContract $billable): ?PaymentMethodContract
    {
        $paymentMethod = $billable->defaultPaymentMethod();

        if (! $paymentMethod) {
            return null;
        }

        return new StripePaymentMethod($paymentMethod, $billable);
    }

    /**
     * Create a setup intent for adding payment methods.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSetupIntent(BillableContract $billable, array $options = []): mixed
    {
        return $billable->createSetupIntent($options);
    }

    /**
     * Verify a webhook signature.
     *
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhookSignature(string $payload, array $headers): bool
    {
        $signature = $headers['Stripe-Signature'] ?? $headers['stripe-signature'] ?? '';
        $secret = $this->webhookSecret();

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        try {
            Webhook::constructEvent($payload, $signature, $secret);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Handle a webhook event.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function handleWebhook(array $payload, array $headers = []): mixed
    {
        // Webhook handling is managed by Laravel Cashier's webhook controller
        // This method is here for custom webhook handling if needed
        return null;
    }

    /**
     * Get the customer portal URL.
     *
     * @param  array<string, mixed>  $options
     */
    public function customerPortalUrl(BillableContract $billable, string $returnUrl, array $options = []): string
    {
        return $billable->billingPortalUrl($returnUrl);
    }

    /**
     * Create a billing portal session.
     *
     * @param  array<string, mixed>  $options
     */
    public function createBillingPortalSession(BillableContract $billable, string $returnUrl, array $options = []): mixed
    {
        return $this->client()->billingPortal->sessions->create(array_merge([
            'customer' => $billable->stripeId(),
            'return_url' => $returnUrl,
        ], $options));
    }

    /**
     * List all plans/prices.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function prices(array $parameters = []): Collection
    {
        $prices = $this->client()->prices->all($parameters);

        $data = $prices->data;

        return collect(is_array($data) ? $data : []);
    }

    /**
     * List all products.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function products(array $parameters = []): Collection
    {
        $products = $this->client()->products->all($parameters);

        $data = $products->data;

        return collect(is_array($data) ? $data : []);
    }

    /**
     * Get a specific price.
     */
    public function price(string $priceId): mixed
    {
        return $this->client()->prices->retrieve($priceId);
    }

    /**
     * Get a specific product.
     */
    public function product(string $productId): mixed
    {
        return $this->client()->products->retrieve($productId);
    }
}
